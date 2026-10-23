<?php

declare(strict_types=1);

namespace App\Work;

use App\Core\Container;
use App\Model\Customer;
use App\Model\Task;
use App\Model\WorkOrder;
use App\Package\PackageCatalogService;
use App\Repository\CustomerContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeRepository;
use App\Support\ItalianFormat as F;

/**
 * Quello che serve vedere delle commesse e delle attivita', gia' pronto
 * per le schermate: la bacheca per stato, la scheda di una commessa con le
 * fasi, le attivita' per giorno. Ogni commessa porta con se' la cosa da
 * fare adesso (sollecitare il cliente, recuperare un ritardo, sbloccare
 * una commessa ferma, consegnare).
 *
 * Le commesse sono facoltative: senza il pacchetto 'work_orders' le
 * attivita' funzionano lo stesso.
 */
final class WorkOrderOverviewService
{
    public const COLUMNS = [
        'da avviare' => ['title' => 'Da avviare', 'tone' => 'neutral'],
        'in corso' => ['title' => 'In corso', 'tone' => 'info'],
        'in attesa del cliente' => ['title' => 'In attesa del cliente', 'tone' => 'warning'],
        'in pausa' => ['title' => 'In pausa', 'tone' => 'neutral'],
        'consegnata' => ['title' => 'Consegnate', 'tone' => 'success'],
    ];

    private TaskRepository $tasks;
    private UserRepository $users;
    private CustomerRepository $customers;
    private CustomerContactRepository $contacts;
    private ?WorkOrderRepository $orders = null;
    private ?WorkOrderTypeRepository $types = null;
    private ?WorkOrderService $service = null;
    private string $today;
    private ?array $userNames = null;
    private bool $hasCustomers;

    public function __construct(Container $container)
    {
        $this->tasks = $container->get(TaskRepository::class);
        $this->users = $container->get(UserRepository::class);
        $this->customers = $container->get(CustomerRepository::class);
        $this->contacts = $container->get(CustomerContactRepository::class);
        $this->hasCustomers = in_array('customers', PackageCatalogService::installedNames(), true);
        if (in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            $this->orders = $container->get(WorkOrderRepository::class);
            $this->types = $container->get(WorkOrderTypeRepository::class);
            $this->service = $container->get(WorkOrderService::class);
        }
        if (in_array('expenses', PackageCatalogService::installedNames(), true)) {
            $this->expenses = $container->get(\App\Repository\ExpenseRepository::class);
        }
        $this->today = date('Y-m-d');
    }

    private ?\App\Repository\ExpenseRepository $expenses = null;

    public function hasWorkOrders(): bool
    {
        return $this->orders !== null;
    }

    /** Bacheca: commesse aperte e consegnate negli ultimi 30 giorni, per stato. */
    public function board(): array
    {
        $orders = $this->orders?->findForBoard(date('Y-m-d', strtotime($this->today . ' -30 days'))) ?? [];
        $tasksByOrder = $this->tasks->findForEntities(WorkOrderService::ENTITY, array_map(static fn (WorkOrder $o) => $o->id, $orders));
        $customerNames = $this->customerNames();
        $typeNames = $this->typeNames();

        $cards = array_map(fn (WorkOrder $o) => $this->card($o, $tasksByOrder[(int) $o->id] ?? [], $customerNames, $typeNames), $orders);

        $columns = [];
        foreach (self::COLUMNS as $stage => $meta) {
            $columns[$stage] = $meta + ['key' => $stage, 'cards' => []];
        }
        foreach ($cards as $card) {
            if (isset($columns[$card['stage']])) {
                $columns[$card['stage']]['cards'][] = $card;
            }
        }
        // "In pausa" compare solo se c'e' qualcosa.
        if ($columns['in pausa']['cards'] === []) {
            unset($columns['in pausa']);
        }

        $active = array_filter($cards, static fn (array $c) => $c['active']);
        $weekEnd = date('Y-m-d', strtotime($this->today . ' +7 days'));

        return [
            'columns' => array_values($columns),
            'totals' => [
                'active' => count($active),
                'dueThisWeek' => count(array_filter($active, static fn (array $c) => $c['dueDate'] !== null && $c['dueDate'] <= $weekEnd)),
                'waiting' => count(array_filter($active, static fn (array $c) => $c['stage'] === 'in attesa del cliente')),
                'oldestWaitingDays' => max([0, ...array_map(static fn (array $c) => $c['waitingDays'] ?? 0, $active)]),
                'late' => count(array_filter($active, static fn (array $c) => $c['flags']['late'])),
                'stale' => count(array_filter($active, static fn (array $c) => $c['flags']['stale'])),
                'deliveredLast30' => count($columns['consegnata']['cards']),
            ],
        ];
    }

    /** La scheda di una commessa: testata, fasi in ordine, tempi, cosa fare. */
    public function detail(WorkOrder $order): array
    {
        $tasks = $this->tasks->findForEntity(WorkOrderService::ENTITY, (int) $order->id);
        $card = $this->card($order, $tasks, $this->customerNames(), $this->typeNames());
        $next = $this->service?->nextStep($tasks);
        $effective = $this->effectiveDays($tasks);

        $estimated = array_sum(array_map(static fn (Task $t) => (int) $t->estimatedMinutes, $tasks));
        $actual = array_sum(array_map(static fn (Task $t) => (int) $t->actualMinutes, $tasks));
        $doneEstimated = array_sum(array_map(static fn (Task $t) => $t->stage === 'fatta' ? (int) $t->estimatedMinutes : 0, $tasks));
        $supplier = $order->supplierCustomerId !== null ? $this->customers->find((int) $order->supplierCustomerId) : null;

        return [
            'card' => $card,
            'steps' => array_map(fn (Task $t) => $this->taskItem($t, [], [], $next !== null && $t->id === $next->id, $effective[(int) $t->id] ?? null), $tasks),
            'hours' => [
                'estimated' => self::duration($estimated),
                'actual' => $actual > 0 ? self::duration($actual) : null,
                // Il confronto ha senso solo sulle fasi fatte e misurate.
                'doneEstimated' => self::duration($doneEstimated),
                'over' => $actual > 0 && $doneEstimated > 0 && $actual > $doneEstimated * 1.2,
            ],
            // Le spese approvate (trasferte, modelle, materiali): il costo vivo della commessa.
            'expenses' => $this->expenses !== null && ($total = $this->expenses->totalForWorkOrder((int) $order->id)) > 0 ? \App\Support\ItalianFormat::euro($total) : null,
            'newExpenseHref' => $this->expenses !== null ? '/spese/nuovo?work_order_id=' . $order->id . '&customer_id=' . $order->customerId : null,
            'supplier' => $supplier instanceof Customer ? ['name' => $supplier->displayName(), 'href' => '/clienti/' . $supplier->id] : null,
            'notes' => $order->notes,
        ];
    }

    /** Commesse di un cliente, per la sua scheda. */
    public function forCustomer(int $customerId): array
    {
        if ($this->orders === null) {
            return [];
        }
        $orders = $this->orders->findByCustomer($customerId);
        $tasksByOrder = $this->tasks->findForEntities(WorkOrderService::ENTITY, array_map(static fn (WorkOrder $o) => $o->id, $orders));

        return array_map(fn (WorkOrder $o) => $this->card($o, $tasksByOrder[(int) $o->id] ?? [], $this->customerNames(), $this->typeNames()), $orders);
    }

    /**
     * Attivita' per giorno: in ritardo, oggi, prossimi 7 giorni, piu'
     * avanti, senza data, fatte negli ultimi 3 giorni.
     */
    public function taskBoard(?int $userId): array
    {
        $tasks = $this->tasks->findForBoard($userId, date('Y-m-d 00:00:00', strtotime($this->today . ' -3 days')));
        $contexts = $this->taskContexts($tasks);

        // Le fasi delle commesse prendono il giorno "vero" (vedi effectiveDays()):
        // una commessa ferma non riempie di falsi ritardi la lista.
        $effective = [];
        $orderIds = array_map(static fn (Task $t) => $t->entityId, array_filter($tasks, static fn (Task $t) => $t->entityType === WorkOrderService::ENTITY));
        foreach ($this->tasks->findForEntities(WorkOrderService::ENTITY, $orderIds) as $orderTasks) {
            $effective += $this->effectiveDays($orderTasks);
        }
        usort($tasks, static function (Task $a, Task $b) use ($effective) {
            $da = $effective[(int) $a->id] ?? $a->day() ?? '9999-12-31';
            $db = $effective[(int) $b->id] ?? $b->day() ?? '9999-12-31';

            return [$da, (int) $a->position, (int) $a->id] <=> [$db, (int) $b->position, (int) $b->id];
        });
        $weekEnd = date('Y-m-d', strtotime($this->today . ' +7 days'));

        $groups = [
            'late' => ['key' => 'late', 'title' => 'In ritardo', 'tone' => 'danger', 'items' => [], 'minutes' => 0],
            // Non dipendono da noi: non sono ritardi nostri, ma vanno tenute d'occhio.
            'client' => ['key' => 'client', 'title' => 'Aspettano il cliente', 'tone' => 'warning', 'items' => [], 'minutes' => 0],
            'today' => ['key' => 'today', 'title' => 'Oggi', 'tone' => 'warning', 'items' => [], 'minutes' => 0],
            'week' => ['key' => 'week', 'title' => 'Prossimi 7 giorni', 'tone' => 'neutral', 'items' => [], 'minutes' => 0],
            'later' => ['key' => 'later', 'title' => 'Piu\' avanti', 'tone' => 'neutral', 'items' => [], 'minutes' => 0],
            'undated' => ['key' => 'undated', 'title' => 'Senza data', 'tone' => 'neutral', 'items' => [], 'minutes' => 0],
            'done' => ['key' => 'done', 'title' => 'Fatte di recente', 'tone' => 'success', 'items' => [], 'minutes' => 0],
        ];

        $waitingClient = 0;
        foreach ($tasks as $task) {
            $day = $effective[(int) $task->id] ?? $task->day();
            $group = match (true) {
                !$task->isOpen() => 'done',
                (int) $task->waitsForClient === 1 && $day !== null && $day <= $this->today => 'client',
                $day === null => 'undated',
                $day < $this->today => 'late',
                $day === $this->today => 'today',
                $day <= $weekEnd => 'week',
                default => 'later',
            };
            $groups[$group]['items'][] = $this->taskItem($task, $contexts, $this->userNames(), false, $effective[(int) $task->id] ?? null);
            $groups[$group]['minutes'] += $task->isOpen() ? (int) $task->estimatedMinutes : 0;
            if ($task->isOpen() && (int) $task->waitsForClient === 1) {
                $waitingClient++;
            }
        }
        foreach ($groups as &$g) {
            $g['minutesLabel'] = $g['minutes'] > 0 ? 'circa ' . self::duration($g['minutes']) : null;
        }
        unset($g);

        return [
            'groups' => array_values(array_filter($groups, static fn (array $g) => $g['items'] !== [] || in_array($g['key'], ['today'], true))),
            'totals' => [
                'late' => count($groups['late']['items']),
                'today' => count($groups['today']['items']),
                'todayMinutes' => $groups['today']['minutes'],
                'week' => count($groups['week']['items']),
                'weekMinutes' => $groups['week']['minutes'],
                'waitingClient' => $waitingClient,
                'doneRecent' => count($groups['done']['items']),
            ],
        ];
    }

    /** @param Task[] $tasks */
    private function card(WorkOrder $order, array $tasks, array $customerNames, array $typeNames): array
    {
        $total = count(array_filter($tasks, static fn (Task $t) => $t->stage !== 'annullata'));
        $done = count(array_filter($tasks, static fn (Task $t) => $t->stage === 'fatta'));
        $next = $this->service?->nextStep($tasks);
        $active = $order->isActive();
        $userNames = $this->userNames();

        $late = $active && $order->dueDate !== null && $order->dueDate < $this->today;
        $lastMove = $order->lastActivityAt ?? $order->updatedAt ?? $order->createdAt;
        $idleDays = $lastMove !== null ? -F::daysFrom(substr($lastMove, 0, 10), $this->today) : 0;
        $stale = $order->stage === 'in corso' && $idleDays >= WorkOrderService::STALE_DAYS;
        $waitingDays = $order->stage === 'in attesa del cliente' && $order->waitingSince !== null ? -F::daysFrom($order->waitingSince, $this->today) : null;

        $dueTone = match (true) {
            !$active || $order->dueDate === null => 'neutral',
            $late => 'danger',
            F::daysFrom($order->dueDate, $this->today) <= 7 => 'warning',
            default => 'neutral',
        };

        $owner = $order->ownerUserId !== null ? ($userNames[(int) $order->ownerUserId] ?? null) : null;
        $customer = $customerNames[(int) $order->customerId] ?? '';

        return [
            'id' => (int) $order->id,
            'href' => '/commesse/' . $order->id,
            'title' => (string) $order->title,
            'customer' => $customer,
            'customerHref' => '/clienti/' . $order->customerId,
            'type' => $order->workOrderTypeId !== null ? ($typeNames[(int) $order->workOrderTypeId] ?? null) : null,
            'stage' => (string) $order->stage,
            'stageLabel' => ucfirst((string) $order->stage),
            'stageTone' => self::COLUMNS[$order->stage]['tone'] ?? 'neutral',
            'priority' => $order->priority,
            'active' => $active,
            'progress' => ['done' => $done, 'total' => $total, 'pct' => $total > 0 ? (int) round($done / $total * 100) : 0],
            'dueDate' => $order->dueDate,
            'due' => $order->dueDate !== null ? [
                'date' => F::shortDate($order->dueDate, $this->today),
                'when' => $order->stage === 'consegnata'
                    ? 'consegnata ' . F::shortDate($order->deliveredAt, $this->today)
                    : ($late ? 'in ritardo di ' . F::plural(-F::daysFrom($order->dueDate, $this->today), 'giorno', 'giorni') : F::relativeDays($order->dueDate, $this->today)),
                'tone' => $dueTone,
            ] : null,
            'startDate' => $order->startDate !== null ? F::shortDate($order->startDate, $this->today) : null,
            'owner' => $owner,
            'ownerId' => $order->ownerUserId,
            'ownerInitials' => $owner !== null ? F::initials($owner) : null,
            'next' => $next !== null ? [
                'title' => (string) $next->title,
                'waits' => (int) $next->waitsForClient === 1,
                'who' => $next->assignedUserId !== null ? ($userNames[(int) $next->assignedUserId] ?? null) : null,
                'day' => $next->day() !== null ? F::shortDate($next->day(), $this->today) : null,
            ] : null,
            'waitingReason' => $order->waitingReason,
            'waitingDays' => $waitingDays,
            'idleDays' => $idleDays,
            'flags' => ['late' => $late, 'stale' => $stale, 'waiting' => $waitingDays !== null],
            'suggestion' => $this->suggestion($order, $tasks, $next, $late, $stale, $waitingDays, $idleDays, $done, $total, $this->projectedEnd($tasks)),
        ];
    }

    /** @param Task[] $tasks */
    /** @param Task[] $tasks @return ?string il giorno in cui finirebbe l'ultima fase, con le date "vere" */
    private function projectedEnd(array $tasks): ?string
    {
        $days = $this->effectiveDays($tasks);

        return $days !== [] ? max($days) : null;
    }

    private function suggestion(WorkOrder $order, array $tasks, ?Task $next, bool $late, bool $stale, ?int $waitingDays, int $idleDays, int $done, int $total, ?string $projectedEnd = null): ?array
    {
        if (!$order->isActive()) {
            return null;
        }

        if ($waitingDays !== null) {
            $reminded = $order->lastReminderAt !== null ? -F::daysFrom(substr($order->lastReminderAt, 0, 10), $this->today) : null;
            $who = $this->decisionMaker((int) $order->customerId);
            $what = "\u{ab}" . ($order->waitingReason ?? 'una risposta') . "\u{bb}";
            if ($reminded !== null && $reminded < 3) {
                return ['tone' => 'info', 'title' => 'Cliente sollecitato', 'text' => "In attesa su {$what}: sollecitato " . ($reminded === 0 ? 'oggi' : F::relativeDays(substr((string) $order->lastReminderAt, 0, 10), $this->today)) . '. Se non arriva niente, risenti ' . $who . ' fra qualche giorno.'];
            }
            if ($waitingDays >= 3) {
                return ['tone' => $waitingDays >= 10 ? 'danger' : 'warning', 'title' => 'Il cliente non ha ancora risposto', 'text' => "Ferma su {$what} da " . F::plural($waitingDays, 'giorno', 'giorni') . ": sollecita {$who}" . ($late ? ', la consegna e\' gia\' in ritardo.' : '.')];
            }

            return ['tone' => 'info', 'title' => 'In attesa del cliente', 'text' => "In attesa su {$what}" . ($waitingDays === 0 ? ' da oggi.' : ' da ' . F::plural($waitingDays, 'giorno', 'giorni') . '.')];
        }

        if ($total > 0 && $done === $total) {
            return ['tone' => 'success', 'title' => 'Tutte le fasi sono fatte', 'text' => 'Se il cliente ha ricevuto tutto, segna la commessa come consegnata.'];
        }

        if ($late) {
            return ['tone' => 'danger', 'title' => 'Consegna in ritardo', 'text' => 'Era prevista il ' . F::shortDate($order->dueDate, $this->today) . ($next !== null ? ". La prossima fase e' \u{ab}{$next->title}\u{bb}: accelerala o concorda una nuova data col cliente." : ': concorda una nuova data col cliente.')];
        }

        if ($projectedEnd !== null && $order->dueDate !== null && $projectedEnd > $order->dueDate) {
            return ['tone' => 'warning', 'title' => 'La consegna rischia di slittare', 'text' => 'Con le fasi rimaste si arriva al ' . F::shortDate($projectedEnd, $this->today) . ', oltre la consegna del ' . F::shortDate($order->dueDate, $this->today) . ': accorcia qualche fase o concorda una nuova data col cliente.'];
        }

        if ($stale) {
            return ['tone' => 'warning', 'title' => 'Commessa ferma', 'text' => 'Nessun movimento da ' . F::plural($idleDays, 'giorno', 'giorni') . ($next !== null ? ": tocca a \u{ab}{$next->title}\u{bb}" . ($next->assignedUserId !== null ? ' (' . ($this->userNames()[(int) $next->assignedUserId] ?? '') . ')' : '') . '.' : '.')];
        }

        if ($order->stage === 'da avviare' && $order->startDate !== null && $order->startDate <= $this->today) {
            return ['tone' => 'warning', 'title' => 'Da avviare', 'text' => 'Doveva partire il ' . F::shortDate($order->startDate, $this->today) . ($next !== null ? ": si comincia da \u{ab}{$next->title}\u{bb}." : '.')];
        }

        if ($next !== null && $next->day() !== null && $next->day() < $this->today) {
            return ['tone' => 'warning', 'title' => 'Fase in ritardo', 'text' => "\u{ab}{$next->title}\u{bb} era prevista il " . F::shortDate($next->day(), $this->today) . '.'];
        }

        return null;
    }

    private function taskItem(Task $task, array $contexts, array $userNames, bool $isNext, ?string $effectiveDay = null): array
    {
        $day = $effectiveDay ?? $task->day();
        $open = $task->isOpen();
        $waits = (int) $task->waitsForClient === 1;
        $tone = match (true) {
            !$open => 'success',
            $day === null => 'neutral',
            // Una fase che aspetta il cliente non e' un ritardo nostro.
            $day < $this->today && $waits => 'warning',
            $day < $this->today => 'danger',
            $day === $this->today => 'warning',
            default => 'neutral',
        };
        $checklist = $task->checklistProgress();
        $context = $contexts[(int) $task->id] ?? null;
        $who = $task->assignedUserId !== null ? ($userNames[(int) $task->assignedUserId] ?? $this->userNames()[(int) $task->assignedUserId] ?? null) : null;

        return [
            'id' => (int) $task->id,
            'href' => '/attivita/' . $task->id,
            'title' => (string) $task->title,
            'done' => !$open,
            'isNext' => $isNext,
            'day' => $day !== null ? F::shortDate($day, $this->today) : null,
            'when' => !$open
                ? 'fatta ' . ($task->completedAt !== null ? F::relativeDays(substr($task->completedAt, 0, 10), $this->today) : '')
                : ($day === null ? null : ($day < $this->today
                    ? ($waits ? 'il cliente doveva rispondere entro il ' . F::shortDate($day, $this->today) : 'in ritardo di ' . F::plural(-F::daysFrom($day, $this->today), 'giorno', 'giorni'))
                    : F::relativeDays($day, $this->today))),
            'tone' => $tone,
            'estimate' => $task->estimatedMinutes ? self::duration((int) $task->estimatedMinutes) : null,
            'actual' => $task->actualMinutes ? self::duration((int) $task->actualMinutes) : null,
            'priority' => $task->priority,
            'waits' => (int) $task->waitsForClient === 1,
            'recurring' => $task->recurrence,
            'who' => $who,
            'whoInitials' => $who !== null ? F::initials($who) : null,
            'checklist' => $open && $checklist['total'] > 0 ? "{$checklist['done']}/{$checklist['total']}" : null,
            'context' => $context['label'] ?? null,
            'contextHref' => $context['href'] ?? null,
            'toggleAction' => '/attivita/' . $task->id . '/fatta',
        ];
    }

    /**
     * Giorni "veri" delle fasi di una commessa: la fase attuale tiene la sua
     * data (se e' passata, e' davvero in ritardo); quelle dopo non possono
     * venire prima di lei, quindi slittano in avanti tutte insieme,
     * mantenendo gli intervalli del modello. Calcolato, mai salvato: domani
     * slittano ancora di un giorno se la commessa resta ferma.
     *
     * @param Task[] $tasks fasi di UNA commessa, in ordine
     * @return array<int,string> id attivita' => giorno
     */
    public function effectiveDays(array $tasks): array
    {
        $days = [];
        $next = null;
        $delta = null;
        foreach ($tasks as $task) {
            $day = $task->day();
            if ($day === null || !$task->isOpen()) {
                continue;
            }
            if ($next === null) {
                $next = $day;
                $days[(int) $task->id] = $day;
                continue;
            }
            if ($delta === null) {
                $base = Workdays::adjust(date('Y-m-d', strtotime(max($next, $this->today) . ' +1 day')));
                $delta = max(0, F::daysFrom($base, $day));
            }
            $days[(int) $task->id] = $delta > 0 ? Workdays::adjust(date('Y-m-d', strtotime("{$day} +{$delta} days"))) : $day;
        }

        return $days;
    }

    /** @param Task[] $tasks @return array<int, array{label:string, href:string}> */
    private function taskContexts(array $tasks): array
    {
        $orderIds = [];
        $customerIds = [];
        foreach ($tasks as $task) {
            if ($task->entityType === WorkOrderService::ENTITY) {
                $orderIds[] = $task->entityId;
            } elseif ($task->entityType === 'customers') {
                $customerIds[] = $task->entityId;
            }
        }
        $titles = $this->orders?->titlesByIds($orderIds) ?? [];
        $orderCustomers = $this->orders?->customersByIds($orderIds) ?? [];
        $customerNames = $this->customerNames();

        $contexts = [];
        foreach ($tasks as $task) {
            $id = (int) $task->entityId;
            if ($task->entityType === WorkOrderService::ENTITY && isset($titles[$id])) {
                $customer = $customerNames[$orderCustomers[$id] ?? 0] ?? null;
                $contexts[(int) $task->id] = ['label' => $titles[$id] . ($customer !== null ? ' · ' . $customer : ''), 'href' => '/commesse/' . $id];
            } elseif ($task->entityType === 'customers' && isset($customerNames[$id])) {
                $contexts[(int) $task->id] = ['label' => $customerNames[$id], 'href' => '/clienti/' . $id];
            }
        }

        return $contexts;
    }

    private function decisionMaker(int $customerId): string
    {
        $contacts = $this->contacts->findByCustomer($customerId);

        return $contacts !== [] ? (string) $contacts[0]->fullName : 'il cliente';
    }

    /** "45 min", "2 h", "1 h 30" */
    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $m === 0 ? "{$h} h" : "{$h} h {$m}";
    }

    /** @return array<int,string> */
    private function customerNames(): array
    {
        static $names = null;
        if ($names === null) {
            $names = [];
            if (!$this->hasCustomers) {
                return $names;
            }
            foreach ($this->customers->findAll() as $customer) {
                $names[(int) $customer->id] = $customer->displayName();
            }
        }

        return $names;
    }

    /** @return array<int,string> */
    private function typeNames(): array
    {
        if ($this->types === null) {
            return [];
        }
        $names = [];
        foreach ($this->types->findAll() as $type) {
            $names[(int) $type->id] = (string) $type->name;
        }

        return $names;
    }

    /** @return array<int,string> */
    private function userNames(): array
    {
        if ($this->userNames === null) {
            $this->userNames = [];
            foreach ($this->users->findAll() as $user) {
                $this->userNames[(int) $user->id] = trim((string) $user->firstName) ?: (string) $user->username;
            }
        }

        return $this->userNames;
    }
}
