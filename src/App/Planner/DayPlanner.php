<?php

declare(strict_types=1);

namespace App\Planner;

use App\Core\Container;
use App\Model\Appointment;
use App\Model\Installment;
use App\Model\Task;
use App\Model\WorkOrder;
use App\Model\WorkSchedule;
use App\Package\PackageCatalogService;
use App\Repository\AppointmentRepository;
use App\Repository\CustomerContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\InstallmentRepository;
use App\Repository\PermissionRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkScheduleRepository;
use App\Support\ItalianFormat as F;
use App\Work\WorkOrderOverviewService;
use App\Work\WorkOrderService;
use App\Work\Workdays;

/**
 * La giornata di una persona, messa in ordine.
 *
 * 1. Gli appuntamenti sono fissi (con il viaggio prima e dopo).
 * 2. Il resto dell'orario, tolta la pausa, sono le finestre libere.
 * 3. Le cose da fare si mettono in fila: prima i solleciti (pochi minuti,
 *    sbloccano il lavoro di altri), poi cio' che e' in ritardo (il piu'
 *    vecchio prima), poi le scadenze di oggi, poi per priorita'; a parita',
 *    le fasi delle commesse da consegnare prima, poi le piu' brevi.
 * 4. Si riempiono le finestre nell'ordine, fino alla parte di giornata da
 *    pianificare (focus_percent); un lavoro lungo si spezza tra due
 *    finestre. Quello che non ci sta resta fuori, con la proposta di
 *    spostarlo al giorno lavorativo dopo.
 *
 * Non salva niente: il piano si ricalcola ogni volta, quindi segue da solo
 * quello che cambia (un'attivita' fatta, un appuntamento spostato).
 */
final class DayPlanner
{
    private const DEFAULT_TASK_MINUTES = 30;
    private const REMINDER_MINUTES = 10;
    /** Un lavoro piu' corto di cosi' non si spezza tra due finestre. */
    private const MIN_SPLIT_MINUTES = 60;

    private TaskRepository $tasks;
    private UserRepository $users;
    private PermissionRepository $permissions;
    private ?WorkScheduleRepository $schedules = null;
    private ?AppointmentRepository $appointments = null;
    private ?WorkOrderRepository $orders = null;
    private ?WorkOrderOverviewService $orderOverview = null;
    private ?InstallmentRepository $installments = null;
    private ?CustomerRepository $customers = null;
    private ?CustomerContactRepository $contacts = null;
    private string $today;
    /** @var array<int, array> cache per persona */
    private array $userData = [];
    private ?array $orderMap = null;
    private int $spareMinutes = 0;
    private ?\App\Worklog\DurationStats $durations = null;

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->tasks = $container->get(TaskRepository::class);
        $this->users = $container->get(UserRepository::class);
        $this->permissions = $container->get(PermissionRepository::class);
        $installed = PackageCatalogService::installedNames();
        if (in_array('planner', $installed, true)) {
            $this->schedules = $container->get(WorkScheduleRepository::class);
        }
        if (in_array('appointments', $installed, true)) {
            $this->appointments = $container->get(AppointmentRepository::class);
        }
        if (in_array('work_orders', $installed, true)) {
            $this->orders = $container->get(WorkOrderRepository::class);
            $this->orderOverview = $container->get(WorkOrderOverviewService::class);
        }
        if (in_array('installments', $installed, true)) {
            $this->installments = $container->get(InstallmentRepository::class);
        }
        if (in_array('customers', $installed, true)) {
            $this->customers = $container->get(CustomerRepository::class);
            $this->contacts = $container->get(CustomerContactRepository::class);
        }
        if (in_array('worklog', $installed, true)) {
            $this->durations = $container->get(\App\Worklog\DurationStats::class);
        }
        $this->today = date('Y-m-d');
    }

    public function schedule(int $userId): WorkSchedule
    {
        return $this->schedules?->forUser($userId) ?? WorkSchedule::standard($userId);
    }

    /**
     * @param ?int $nowMinute per oggi: da che minuto pianificare (default: adesso)
     */
    public function plan(int $userId, string $date, ?int $nowMinute = null): array
    {
        $schedule = $this->schedule($userId);
        $isToday = $date === $this->today;
        $isPast = $date < $this->today;
        $working = $schedule->worksOn($date);
        // Ferie o malattia: niente lavoro pianificato; un permesso toglie le sue ore.
        $absence = $this->absenceFor($userId, $date);
        if ($absence !== null && $absence['full']) {
            $working = false;
        }

        $dayStart = WorkSchedule::minutes($schedule->startTime, 540);
        $dayEnd = WorkSchedule::minutes($schedule->endTime, 1080);
        $breakStart = WorkSchedule::minutes($schedule->breakStart, -1);
        $breakEnd = WorkSchedule::minutes($schedule->breakEnd, -1);
        $hasBreak = $breakStart >= 0 && $breakEnd > $breakStart;
        $workMinutes = max(0, $dayEnd - $dayStart - ($hasBreak ? $breakEnd - $breakStart : 0));
        $focus = max(10, min(100, (int) ($schedule->focusPercent ?? 85)));

        // 1. appuntamenti (fissi), con il viaggio
        $appointments = $this->appointmentsFor($userId, $date);
        $busy = [];
        $appointmentMinutes = 0;
        foreach ($appointments as $a) {
            $start = $this->minuteOfDay((string) $a->startAt, $date);
            $end = $this->minuteOfDay($a->end(), $date);
            $travel = (int) $a->travelMinutes;
            $busy[] = [max(0, $start - $travel), min(1440, $end + $travel)];
            $appointmentMinutes += max(0, min($end + $travel, $dayEnd) - max($start - $travel, $dayStart));
        }
        if ($hasBreak) {
            $busy[] = [$breakStart, $breakEnd];
        }
        if ($absence !== null && !$absence['full']) {
            $busy[] = [$absence['from'], $absence['to']];
            $appointmentMinutes += max(0, min($absence['to'], $dayEnd) - max($absence['from'], $dayStart));
        }

        // 2. finestre libere (oggi: da adesso in poi)
        $from = $dayStart;
        if ($isToday) {
            $now = $nowMinute ?? ((int) date('G') * 60 + (int) date('i'));
            $from = max($dayStart, (int) (ceil($now / 15) * 15));
        }
        $windows = $working && !$isPast ? self::freeWindows($from, $dayEnd, $busy) : [];

        // 3. le cose da fare, in fila (senza le fasi che si fanno durante
        //    un appuntamento della stessa commessa, es. la giornata di shooting)
        [$items, $waiting] = $this->itemsFor($userId, $date);
        $covered = $this->coveredByAppointments($items, $appointments, $date);
        $items = array_values(array_filter($items, static fn (array $i) => !isset($covered[$i['id']])));
        usort($items, static fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        // 4. riempimento fino alla parte di giornata da pianificare
        $capacity = (int) round($workMinutes * $focus / 100) - $appointmentMinutes;
        if ($isToday) {
            $capacity = min($capacity, array_sum(array_map(static fn (array $w) => $w[1] - $w[0], $windows)));
        }
        $capacity = max(0, $capacity);

        $placed = [];
        $overflow = [];
        $used = 0;
        foreach ($items as $item) {
            $need = $item['minutes'];
            $left = intdiv(max(0, $capacity - $used), 15) * 15;
            // Un lavoro lungo che non ci sta tutto: oggi la parte che ci
            // sta (almeno un'ora), il resto resta fuori per il giorno dopo.
            if ($working && $need >= self::MIN_SPLIT_MINUTES && $need > $left + 15 && $left >= self::MIN_SPLIT_MINUTES) {
                $slots = self::take($windows, $left, true);
                if ($slots !== null) {
                    $used += $left;
                    foreach ($slots as [$s, $e]) {
                        $placed[] = $item + ['start' => $s, 'end' => $e, 'part' => 'oggi ' . WorkOrderOverviewService::duration($left) . ' su ' . WorkOrderOverviewService::duration($need)];
                    }
                    $overflow[] = ['minutes' => $need - $left, 'continues' => true] + $item;
                    continue;
                }
            }
            if (!$working || $used + $need > $capacity + 15) {
                $overflow[] = $item;
                continue;
            }
            $slots = self::take($windows, $need, $need >= self::MIN_SPLIT_MINUTES);
            if ($slots === null) {
                $overflow[] = $item;
                continue;
            }
            $used += $need;
            foreach ($slots as $i => [$s, $e]) {
                $placed[] = $item + ['start' => $s, 'end' => $e, 'part' => count($slots) > 1 ? ($i + 1) . '/' . count($slots) : null];
            }
        }

        $this->spareMinutes = $capacity - $used;
        $timeline = $this->timeline($appointments, $placed, $date, $covered, $hasBreak ? [$breakStart, $breakEnd] : null, $working ? $windows : [], $from, $dayEnd);
        $overflowMinutes = array_sum(array_column($overflow, 'minutes'));
        $nextWorkday = $this->nextWorkday($schedule, $date, $userId);

        return [
            'userId' => $userId,
            'date' => $date,
            'isToday' => $isToday,
            'working' => $working,
            'absence' => $absence['label'] ?? null,
            'hours' => ['start' => self::clock($dayStart), 'end' => self::clock($dayEnd)],
            'capacityMinutes' => (int) round($workMinutes * $focus / 100),
            'appointmentMinutes' => $appointmentMinutes,
            'plannedMinutes' => $used,
            'freeMinutes' => max(0, $capacity - $used),
            'overflowMinutes' => $overflowMinutes,
            // Gli appuntamenti pesano sull'orario intero (una giornata di
            // shooting e' una giornata piena, non un sovraccarico), il lavoro
            // sulla parte pianificabile.
            'loadPercent' => $workMinutes > 0
                ? (int) round(min(100, $appointmentMinutes / $workMinutes * 100) + ($used + $overflowMinutes) / max(1, $workMinutes * $focus / 100) * 100)
                : 0,
            'timeline' => $timeline,
            'placed' => $placed,
            'overflow' => array_map(fn (array $i) => $this->publicItem($i), $overflow),
            'waiting' => $waiting,
            'appointments' => count($appointments),
            'lateCount' => count(array_filter($items, static fn (array $i) => $i['late'] > 0)),
            'nextWorkday' => $nextWorkday,
            'nextWorkdayLabel' => F::relativeDays($nextWorkday, $date) === 'domani' ? 'domani' : $this->dayName($nextWorkday),
        ];
    }

    /**
     * Le cose da fare quel giorno: attivita' e fasi (oggi anche quelle
     * rimaste indietro) e i solleciti; a parte quelle che aspettano il
     * cliente.
     *
     * @return array{0: list<array>, 1: list<array>}
     */
    private function itemsFor(int $userId, string $date): array
    {
        $data = $this->userData($userId);
        $isToday = $date === $this->today;
        // Oggi, e il prossimo giorno di lavoro, si portano dietro quello
        // rimasto indietro: domattina sara' ancora li' da fare.
        $carry = $isToday || $date === $this->nextWorkday($this->schedule($userId), $this->today);
        $orders = $this->orderMap();

        $items = [];
        $waiting = [];
        foreach ($data['tasks'] as $task) {
            if (!$task->isOpen()) {
                continue;
            }
            $day = $data['effective'][(int) $task->id] ?? $task->day();
            $belongs = $day === $date || ($carry && $day !== null && $day < $date);
            if (!$belongs) {
                continue;
            }

            $order = $task->entityType === WorkOrderService::ENTITY ? ($orders[(int) $task->entityId] ?? null) : null;
            $context = $order !== null ? $order['title'] . ($order['customer'] ? ' · ' . $order['customer'] : '') : $this->customerContext($task);

            if ((int) $task->waitsForClient === 1) {
                $waiting[] = ['title' => (string) $task->title, 'context' => $context, 'href' => $order !== null ? '/commesse/' . $task->entityId : '/attivita/' . $task->id];
                continue;
            }

            $late = $day < $date ? -F::daysFrom($day, $date) : 0;
            $deadlineToday = $task->dueAt !== null && substr($task->dueAt, 0, 10) <= $date;
            $priority = ['alta' => 0, 'normale' => 1, 'bassa' => 2][$task->priority ?? 'normale'] ?? 1;
            $group = $late > 0 ? 1 : ($deadlineToday ? 2 : 3 + $priority);
            // Con abbastanza casi nel registro vale il tempo vero, non la stima.
            $learned = $this->durations?->learnedMinutes($task);
            $minutes = max(15, (int) ($learned ?? ($task->estimatedMinutes ?: self::DEFAULT_TASK_MINUTES)));

            $items[] = [
                'kind' => 'task',
                'id' => (int) $task->id,
                'orderId' => $order !== null ? (int) $task->entityId : null,
                'title' => (string) $task->title,
                'context' => $context,
                'href' => '/attivita/' . $task->id,
                'contextHref' => $order !== null ? '/commesse/' . $task->entityId : null,
                'minutes' => $minutes,
                'late' => $late,
                'priority' => $task->priority,
                'deadline' => $deadlineToday ? substr((string) $task->dueAt, 11, 5) : null,
                'action' => '/attivita/' . $task->id . '/fatta',
                'movable' => true,
                'learned' => $learned !== null && $learned !== (int) $task->estimatedMinutes,
                'sort' => [$group, -$late, $order['due'] ?? '9999-12-31', $minutes, (int) $task->position, (int) $task->id],
            ];
        }

        if ($isToday) {
            array_push($items, ...$this->reminders($userId));
        }

        return [$items, $waiting];
    }

    /**
     * Solleciti da fare oggi: commesse ferme sul cliente da 3+ giorni senza
     * sollecito recente (al responsabile) e, per chi segue i pagamenti, gli
     * incassi scaduti.
     */
    private function reminders(int $userId): array
    {
        $items = [];
        if ($this->orders !== null) {
            foreach ($this->orders->findAll(['owner_user_id' => $userId, 'stage' => 'in attesa del cliente']) as $order) {
                /** @var WorkOrder $order */
                $waitingDays = $order->waitingSince !== null ? -F::daysFrom($order->waitingSince, $this->today) : 0;
                $remindedDays = $order->lastReminderAt !== null ? -F::daysFrom(substr($order->lastReminderAt, 0, 10), $this->today) : null;
                if ($waitingDays < 3 || ($remindedDays !== null && $remindedDays < 3)) {
                    continue;
                }
                $who = $this->decisionMaker((int) $order->customerId);
                $items[] = [
                    'kind' => 'reminder',
                    'id' => 'wo' . $order->id,
                    'title' => "Sollecita {$who}: \u{ab}{$order->waitingReason}\u{bb}",
                    'context' => $order->title . ' · ferma da ' . F::plural($waitingDays, 'giorno', 'giorni'),
                    'href' => '/commesse/' . $order->id,
                    'contextHref' => null,
                    'minutes' => self::REMINDER_MINUTES,
                    'late' => 0,
                    'priority' => 'alta',
                    'deadline' => null,
                    'action' => '/commesse/' . $order->id . '/sollecitato',
                    'actionLabel' => 'Ho sollecitato',
                    'movable' => false,
                    'sort' => [0, -$waitingDays, '', self::REMINDER_MINUTES, 0, (int) $order->id],
                ];
            }
        }

        if ($this->installments !== null && in_array('installments.edit', $this->userData($userId)['permissions'], true)) {
            foreach ($this->installments->findOpenUntil(date('Y-m-d', strtotime($this->today . ' -1 day'))) as $installment) {
                /** @var Installment $installment */
                if ($this->paymentFollower((int) $installment->customerId) !== $userId) {
                    continue;
                }
                $remindedDays = $installment->reminderSentAt !== null ? -F::daysFrom(substr($installment->reminderSentAt, 0, 10), $this->today) : null;
                if ($remindedDays !== null && $remindedDays < 5) {
                    continue;
                }
                $customer = $this->customers?->find((int) $installment->customerId);
                $days = -F::daysFrom((string) $installment->dueDate, $this->today);
                $items[] = [
                    'kind' => 'reminder',
                    'id' => 'in' . $installment->id,
                    'title' => 'Sollecita il pagamento: ' . ($customer?->displayName() ?? 'cliente') . ', ' . F::euro((float) $installment->amount),
                    'context' => $installment->description . ' · scaduto da ' . F::plural($days, 'giorno', 'giorni'),
                    'href' => '/clienti/' . $installment->customerId,
                    'contextHref' => null,
                    'minutes' => self::REMINDER_MINUTES,
                    'late' => 0,
                    'priority' => 'alta',
                    'deadline' => null,
                    'action' => '/scadenzario/' . $installment->id . '/sollecitato',
                    'actionLabel' => 'Ho sollecitato',
                    'movable' => false,
                    'sort' => [0, -$days, '', self::REMINDER_MINUTES, 0, (int) $installment->id],
                ];
            }
        }

        // Contenuti in revisione interna: a chi puo' approvarli, una voce sola (si approvano dal piano, cliente per cliente).
        if (in_array('editorial', PackageCatalogService::installedNames(), true) && in_array('editorial.approve', $this->userData($userId)['permissions'], true)) {
            $queue = $this->container->get(\App\Editorial\EditorialOverviewService::class)->reviewQueue();
            $count = array_sum(array_column($queue, 'count'));
            if ($count > 0) {
                $items[] = [
                    'kind' => 'reminder',
                    'id' => 'edreview',
                    'title' => 'Approva i contenuti in revisione: ' . F::plural($count, 'contenuto', 'contenuti'),
                    'context' => implode(', ', array_map(static fn (array $q) => "{$q['name']} ({$q['count']})", array_slice($queue, 0, 4))),
                    'href' => '/piano-editoriale',
                    'contextHref' => null,
                    'minutes' => 5 * min(6, $count),
                    'late' => 0,
                    'priority' => 'alta',
                    'deadline' => null,
                    'action' => null,
                    'actionHref' => '/piano-editoriale',
                    'actionLabel' => 'Apri',
                    'movable' => false,
                    'sort' => [0, -$count, '', 5, 0, 0],
                ];
            }
        }

        // Richieste dei clienti ancora da smistare: a chi coordina, una voce sola.
        if (in_array('client_requests', PackageCatalogService::installedNames(), true) && in_array('client_requests.assign', $this->userData($userId)['permissions'], true)) {
            $waiting = array_filter(
                $this->container->get(\App\Repository\ClientRequestRepository::class)->findAll(['stage' => 'nuova']),
                static fn ($r) => $r->assignedUserId === null
            );
            if ($waiting !== []) {
                $items[] = [
                    'kind' => 'reminder',
                    'id' => 'crassign',
                    'title' => 'Smista le richieste dei clienti: ' . F::plural(count($waiting), 'nuova', 'nuove'),
                    'context' => implode(', ', array_slice(array_map(static fn ($r) => (string) $r->summary, $waiting), 0, 3)),
                    'href' => '/richieste',
                    'contextHref' => null,
                    'minutes' => 3 * min(5, count($waiting)),
                    'late' => 0,
                    'priority' => 'alta',
                    'deadline' => null,
                    'action' => null,
                    'actionHref' => '/richieste',
                    'actionLabel' => 'Apri',
                    'movable' => false,
                    'sort' => [0, -count($waiting), '', 3, 0, 0],
                ];
            }
        }

        // Ferie e spese da approvare: a chi le approva.
        foreach ([
            ['leaves', 'leaves.approve', \App\Repository\LeaveRequestRepository::class, ['stage' => 'richiesta'], 'ferie e permessi da approvare', '/ferie'],
            ['expenses', 'expenses.approve', \App\Repository\ExpenseRepository::class, ['stage' => 'da approvare'], 'spese da approvare', '/spese'],
            ['quotes', 'quotes.approve', \App\Repository\QuoteRepository::class, ['stage' => 'da approvare'], 'preventivi da approvare', '/preventivi'],
        ] as [$package, $permission, $repository, $where, $label, $href]) {
            if (!in_array($package, PackageCatalogService::installedNames(), true) || !in_array($permission, $this->userData($userId)['permissions'], true)) {
                continue;
            }
            $count = count($this->container->get($repository)->findAll($where));
            if ($count > 0) {
                $items[] = [
                    'kind' => 'reminder',
                    'id' => 'approve-' . $package,
                    'title' => ucfirst($label) . ": {$count}",
                    'context' => null,
                    'href' => $href,
                    'contextHref' => null,
                    'minutes' => 5,
                    'late' => 0,
                    'priority' => 'normale',
                    'deadline' => null,
                    'action' => null,
                    'actionHref' => $href,
                    'actionLabel' => 'Apri',
                    'movable' => false,
                    'sort' => [0, 0, '', 5, 0, 0],
                ];
            }
        }

        // Clienti a rischio seguiti da questa persona e non sentiti nell'ultima settimana.
        if (\App\ClientHealth\HealthScore::isAvailable()) {
            foreach ($this->health()->all() as $row) {
                if ($row['level'] !== 'rischio' || $row['accountUserId'] !== $userId || ($row['silentDays'] !== null && $row['silentDays'] < 7)) {
                    continue;
                }
                $items[] = [
                    'kind' => 'reminder',
                    'id' => 'ch' . $row['customerId'],
                    'title' => 'Senti ' . $row['name'] . ': ' . lcfirst(rtrim($row['suggestion'], '.')),
                    'context' => ucfirst(implode(' · ', array_slice($row['reasons'], 0, 2))),
                    'href' => $row['href'],
                    'contextHref' => null,
                    'minutes' => self::REMINDER_MINUTES,
                    'late' => 0,
                    'priority' => 'alta',
                    'deadline' => null,
                    'action' => null,
                    'actionHref' => '/clienti/contatti/nuovo?customer_id=' . $row['customerId'],
                    'actionLabel' => "Com'e' andata",
                    'actionIcon' => 'phone',
                    'movable' => false,
                    'sort' => [0, $row['score'] - 100, '', self::REMINDER_MINUTES, 0, $row['customerId']],
                ];
            }
        }

        return $items;
    }

    /** @return ?array{full:bool, from:?int, to:?int, label:string} */
    private function absenceFor(int $userId, string $date): ?array
    {
        if (!in_array('leaves', PackageCatalogService::installedNames(), true)) {
            return null;
        }

        return $this->container->get(\App\Leaves\LeaveService::class)->absenceFor($userId, $date);
    }

    private ?\App\ClientHealth\HealthScore $health = null;

    private function health(): \App\ClientHealth\HealthScore
    {
        return $this->health ??= $this->container->get(\App\ClientHealth\HealthScore::class);
    }

    /**
     * La giornata in ordine di orario: appuntamenti (con viaggio), lavoro,
     * pausa, tempo libero.
     */
    /**
     * Fasi di una commessa che coincidono con un suo appuntamento quel
     * giorno (stessa commessa, stesso giorno, durata almeno meta'
     * dell'appuntamento): si fanno li', non occupano altro tempo.
     *
     * @return array<int, array{appointmentId:int, item:array}> id attivita' => appuntamento
     */
    private function coveredByAppointments(array $items, array $appointments, string $date): array
    {
        $covered = [];
        foreach ($appointments as $a) {
            if ($a->entityType !== WorkOrderService::ENTITY || $a->entityId === null) {
                continue;
            }
            $duration = $this->minuteOfDay($a->end(), $date) - $this->minuteOfDay((string) $a->startAt, $date);
            foreach ($items as $item) {
                if ($item['kind'] === 'task' && ($item['orderId'] ?? null) === (int) $a->entityId && $item['late'] === 0
                    && $item['minutes'] >= $duration / 2 && !isset($covered[$item['id']])) {
                    $covered[$item['id']] = ['appointmentId' => (int) $a->id, 'item' => $item];
                }
            }
        }

        return $covered;
    }

    private function timeline(array $appointments, array $placed, string $date, array $covered, ?array $break, array $windowsLeft, int $from, int $dayEnd): array
    {
        $rows = [];
        foreach ($appointments as $a) {
            /** @var Appointment $a */
            $start = $this->minuteOfDay((string) $a->startAt, $date);
            $end = $this->minuteOfDay($a->end(), $date);
            $customer = $a->customerId !== null ? $this->customers?->find((int) $a->customerId) : null;
            if ((int) $a->travelMinutes > 0) {
                $rows[] = ['type' => 'travel', 'start' => $start - (int) $a->travelMinutes, 'end' => $start, 'title' => 'Viaggio' . ($a->location ? ' verso ' . self::place($a->location) : '')];
            }
            $rows[] = [
                'type' => 'appointment',
                'start' => $start,
                'end' => $end,
                'title' => (string) $a->title,
                'context' => implode(' · ', array_filter([$customer?->displayName(), $a->location])),
                'href' => '/appuntamenti/' . $a->id,
                'online' => (int) $a->isOnline === 1,
                'meetUrl' => $a->meetUrl,
                'checklist' => count(array_filter(array_map('trim', explode("\n", (string) $a->checklist)))),
                'steps' => array_values(array_map(
                    fn (array $c) => $this->publicItem($c['item']),
                    array_filter($covered, static fn (array $c) => $c['appointmentId'] === (int) $a->id)
                )),
            ];
            if ((int) $a->travelMinutes > 0) {
                $rows[] = ['type' => 'travel', 'start' => $end, 'end' => $end + (int) $a->travelMinutes, 'title' => 'Rientro'];
            }
        }
        foreach ($placed as $item) {
            $rows[] = ['type' => $item['kind'], 'start' => $item['start'], 'end' => $item['end']] + $this->publicItem($item) + ['part' => $item['part']];
        }
        if ($break !== null && $break[1] > $from) {
            $rows[] = ['type' => 'break', 'start' => max($break[0], $from), 'end' => $break[1], 'title' => 'Pausa'];
        }
        // Spazi rimasti di almeno mezz'ora: liberi davvero, o (se la parte
        // pianificabile e' gia' piena) il margine per telefonate e imprevisti.
        foreach ($windowsLeft as [$s, $e]) {
            if ($e - $s >= 30) {
                $rows[] = ['type' => 'free', 'start' => $s, 'end' => $e, 'title' => $this->spareMinutes >= 30 ? 'Libero' : 'Margine per telefonate e imprevisti'];
            }
        }

        usort($rows, static fn (array $a, array $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        return array_map(static fn (array $r) => $r + ['time' => self::clock($r['start']) . '-' . self::clock($r['end']), 'minutes' => $r['end'] - $r['start']], $rows);
    }

    private function publicItem(array $item): array
    {
        return [
            'kind' => $item['kind'],
            'id' => $item['id'],
            'title' => $item['title'],
            'context' => $item['context'],
            'href' => $item['href'],
            'contextHref' => $item['contextHref'],
            'minutes' => $item['minutes'],
            'duration' => WorkOrderOverviewService::duration($item['minutes']),
            'late' => $item['late'] > 0 ? 'in ritardo di ' . F::plural($item['late'], 'giorno', 'giorni') : null,
            'priority' => $item['priority'],
            'deadline' => $item['deadline'],
            'action' => $item['action'],
            'actionLabel' => $item['actionLabel'] ?? null,
            'actionHref' => $item['actionHref'] ?? null,
            'actionIcon' => $item['actionIcon'] ?? null,
            // Il resto di un lavoro iniziato oggi: non si sposta (prosegue da solo).
            'movable' => $item['movable'] && !($item['continues'] ?? false),
            'continues' => $item['continues'] ?? false,
            'learned' => $item['learned'] ?? false,
        ];
    }

    /**
     * Toglie dalle finestre il tempo per un lavoro: tutto in una finestra,
     * o (se si puo' spezzare) in piu' pezzi di almeno mezz'ora.
     *
     * @param list<array{0:int,1:int}> $windows modificate sul posto
     * @return ?list<array{0:int,1:int}>
     */
    private static function take(array &$windows, int $minutes, bool $splittable): ?array
    {
        foreach ($windows as $i => [$s, $e]) {
            if ($e - $s >= $minutes) {
                $windows[$i] = [$s + $minutes, $e];

                return [[$s, $s + $minutes]];
            }
        }
        if (!$splittable) {
            return null;
        }
        $available = array_sum(array_map(static fn (array $w) => $w[1] - $w[0] >= 30 ? $w[1] - $w[0] : 0, $windows));
        if ($available < $minutes) {
            return null;
        }

        $slots = [];
        $left = $minutes;
        foreach ($windows as $i => [$s, $e]) {
            if ($left <= 0 || $e - $s < 30) {
                continue;
            }
            $use = min($left, $e - $s);
            $slots[] = [$s, $s + $use];
            $windows[$i] = [$s + $use, $e];
            $left -= $use;
        }

        return $slots;
    }

    /** @param list<array{0:int,1:int}> $busy @return list<array{0:int,1:int}> */
    private static function freeWindows(int $from, int $to, array $busy): array
    {
        usort($busy, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        $windows = [];
        $cursor = $from;
        foreach ($busy as [$s, $e]) {
            if ($e <= $cursor) {
                continue;
            }
            if ($s > $cursor) {
                $windows[] = [$cursor, min($s, $to)];
            }
            $cursor = max($cursor, $e);
            if ($cursor >= $to) {
                break;
            }
        }
        if ($cursor < $to) {
            $windows[] = [$cursor, $to];
        }

        return array_values(array_filter($windows, static fn (array $w) => $w[1] - $w[0] >= 15));
    }

    /** @return Appointment[] */
    public function appointmentsFor(int $userId, string $date): array
    {
        if ($this->appointments === null) {
            return [];
        }

        return array_values(array_filter(
            $this->appointments->findBetween("{$date} 00:00:00", date('Y-m-d', strtotime("{$date} +1 day")) . ' 00:00:00'),
            static fn (Appointment $a) => $a->isActive() && in_array($userId, $a->peopleIds(), true)
        ));
    }

    private function userData(int $userId): array
    {
        if (!isset($this->userData[$userId])) {
            $tasks = $this->tasks->findForBoard($userId, $this->today . ' 00:00:00');
            $effective = [];
            if ($this->orderOverview !== null) {
                $orderIds = array_map(static fn (Task $t) => $t->entityId, array_filter($tasks, static fn (Task $t) => $t->entityType === WorkOrderService::ENTITY));
                foreach ($this->tasks->findForEntities(WorkOrderService::ENTITY, $orderIds) as $orderTasks) {
                    $effective += $this->orderOverview->effectiveDays($orderTasks);
                }
            }
            $user = $this->users->find($userId);
            $this->userData[$userId] = [
                'tasks' => $tasks,
                'effective' => $effective,
                'permissions' => $user !== null ? $this->permissions->findEffectiveCodesForUser($userId, (int) $user->profileId) : [],
            ];
        }

        return $this->userData[$userId];
    }

    /** @return array<int, array{title:string, customer:?string, due:?string}> */
    private function orderMap(): array
    {
        if ($this->orderMap === null) {
            $this->orderMap = [];
            foreach ($this->orders?->findAll() ?? [] as $order) {
                $customer = $order->customerId !== null ? $this->customers?->find((int) $order->customerId) : null;
                $this->orderMap[(int) $order->id] = ['title' => (string) $order->title, 'customer' => $customer?->displayName(), 'due' => $order->dueDate];
            }
        }

        return $this->orderMap;
    }

    /**
     * Chi sollecita un pagamento: il referente interno del cliente, se
     * puo' seguire gli incassi; altrimenti il primo che puo'.
     */
    private function paymentFollower(int $customerId): ?int
    {
        $customer = $this->customers?->find($customerId);
        if ($customer !== null && $customer->accountUserId !== null
            && in_array('installments.edit', $this->userData((int) $customer->accountUserId)['permissions'], true)) {
            return (int) $customer->accountUserId;
        }
        foreach ($this->users->findAll([], 'id') as $user) {
            if (in_array('installments.edit', $this->userData((int) $user->id)['permissions'], true)) {
                return (int) $user->id;
            }
        }

        return null;
    }

    public function customerName(int $customerId): ?string
    {
        return $this->customers?->find($customerId)?->displayName();
    }

    private function customerContext(Task $task): ?string
    {
        if ($task->entityType === 'customers' && $task->entityId !== null) {
            return $this->customers?->find((int) $task->entityId)?->displayName();
        }

        return null;
    }

    private function decisionMaker(int $customerId): string
    {
        $contacts = $this->contacts?->findByCustomer($customerId) ?? [];

        return $contacts !== [] ? (string) $contacts[0]->fullName : ($this->customers?->find($customerId)?->displayName() ?? 'il cliente');
    }

    private function nextWorkday(WorkSchedule $schedule, string $date, ?int $userId = null): string
    {
        $next = $date;
        for ($i = 0; $i < 30; $i++) {
            $next = date('Y-m-d', strtotime("{$next} +1 day"));
            if ($schedule->worksOn($next) && ($userId === null || !($this->absenceFor($userId, $next)['full'] ?? false))) {
                return $next;
            }
        }

        return Workdays::adjust(date('Y-m-d', strtotime("{$date} +1 day")));
    }

    private function minuteOfDay(string $datetime, string $date): int
    {
        $diff = (int) round((strtotime($datetime) - strtotime("{$date} 00:00:00")) / 60);

        return max(0, min(1440, $diff));
    }

    public function dayName(string $date): string
    {
        $names = ['lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'];

        return $names[(int) date('N', strtotime($date)) - 1] . ' ' . F::shortDate($date, $this->today);
    }

    public static function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** "Via Garibaldi 21, Parma" -> "Parma" */
    public static function place(string $location): string
    {
        $parts = array_map('trim', explode(',', $location));
        $last = (string) end($parts);
        $last = trim(preg_replace('/\b\d{5}\b|\([A-Z]{2}\)/', '', $last));

        return $last !== '' ? $last : $location;
    }
}
