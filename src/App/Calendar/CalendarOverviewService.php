<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Core\Container;
use App\Model\Appointment;
use App\Package\PackageCatalogService;
use App\Repository\AppointmentRepository;
use App\Repository\CustomerRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Support\ItalianFormat as F;
use App\Work\WorkOrderOverviewService;

/**
 * Il calendario gia' pronto per le schermate: la settimana con gli
 * appuntamenti posizionati per ora (affiancati se si sovrappongono), il
 * tempo di viaggio prima e dopo, i conflitti (la stessa persona in due
 * posti), quante attivita' ci sono ogni giorno; la scheda di un
 * appuntamento.
 */
final class CalendarOverviewService
{
    public const KINDS = [
        'shooting' => ['label' => 'Shooting', 'color' => 'brass', 'away' => true],
        'riprese' => ['label' => 'Riprese', 'color' => 'lilac', 'away' => true],
        'incontro dal cliente' => ['label' => 'Dal cliente', 'color' => 'teal', 'away' => true],
        'sopralluogo' => ['label' => 'Sopralluogo', 'color' => 'teal', 'away' => true],
        'evento' => ['label' => 'Evento', 'color' => 'brass', 'away' => true],
        'incontro in sede' => ['label' => 'In sede', 'color' => 'moss', 'away' => false],
        'call' => ['label' => 'Call', 'color' => 'petrol', 'away' => false],
        'altro' => ['label' => 'Altro', 'color' => 'neutral', 'away' => false],
    ];

    private const DAY_NAMES = ['lun', 'mar', 'mer', 'gio', 'ven', 'sab', 'dom'];
    private const DAY_NAMES_LONG = ['lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'];

    private AppointmentRepository $appointments;
    private UserRepository $users;
    private ?TaskRepository $tasks = null;
    private ?CustomerRepository $customers = null;
    private ?WorkOrderRepository $workOrders = null;
    private ?array $people = null;
    private string $today;

    public function __construct(Container $container)
    {
        $this->appointments = $container->get(AppointmentRepository::class);
        $this->users = $container->get(UserRepository::class);
        $installed = PackageCatalogService::installedNames();
        if (in_array('tasks', $installed, true)) {
            $this->tasks = $container->get(TaskRepository::class);
        }
        if (in_array('customers', $installed, true)) {
            $this->customers = $container->get(CustomerRepository::class);
        }
        if (in_array('work_orders', $installed, true)) {
            $this->workOrders = $container->get(WorkOrderRepository::class);
        }
        $this->today = date('Y-m-d');
    }

    /** Lunedi' della settimana che contiene $date. */
    public static function weekStart(string $date): string
    {
        return date('Y-m-d', strtotime('monday this week', strtotime($date)));
    }

    public function week(string $monday): array
    {
        $sunday = date('Y-m-d', strtotime("{$monday} +6 days"));
        $list = $this->appointments->findBetween("{$monday} 00:00:00", date('Y-m-d', strtotime("{$sunday} +1 day")) . ' 00:00:00');
        $conflicts = $this->conflicts($list);
        $taskLoad = $this->tasks?->openPerDay($monday, $sunday) ?? [];

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime("{$monday} +{$i} days"));
            $days[$date] = [
                'date' => $date,
                'label' => self::DAY_NAMES[$i] . ' ' . (int) date('j', strtotime($date)),
                'isToday' => $date === $this->today,
                'isWeekend' => $i >= 5,
                'tasks' => isset($taskLoad[$date]) ? [
                    'count' => $taskLoad[$date]['count'],
                    'label' => F::plural($taskLoad[$date]['count'], "attivita'", "attivita'") . ($taskLoad[$date]['minutes'] > 0 ? ' · ' . WorkOrderOverviewService::duration($taskLoad[$date]['minutes']) : ''),
                ] : null,
                'events' => [],
                'newHref' => '/appuntamenti/nuovo?start_at=' . $date . 'T09:00',
            ];
        }

        $minHour = 8;
        $maxHour = 19;
        $awayMinutes = 0;
        $counts = [];
        foreach ($list as $appointment) {
            $item = $this->eventItem($appointment, $conflicts[(int) $appointment->id] ?? null);
            $start = strtotime((string) $appointment->startAt);
            $end = strtotime($appointment->end());
            if ($appointment->isActive()) {
                $counts[$item['kind']] = ($counts[$item['kind']] ?? 0) + 1;
                if ($item['away']) {
                    $awayMinutes += (int) round(($end - $start) / 60) + 2 * (int) $appointment->travelMinutes;
                }
            }

            // Un appuntamento su piu' giorni compare in ognuno, tagliato al giorno.
            for ($day = strtotime(date('Y-m-d', $start)); $day < $end; $day = strtotime('+1 day', $day)) {
                $date = date('Y-m-d', $day);
                if (!isset($days[$date])) {
                    continue;
                }
                $from = max($start, $day);
                $to = min($end, strtotime('+1 day', $day));
                $piece = $item + [
                    'startMin' => (int) (($from - $day) / 60),
                    'endMin' => max((int) (($to - $day) / 60), (int) (($from - $day) / 60) + 30),
                ];
                $minHour = min($minHour, intdiv(max(0, $piece['startMin'] - $piece['travel']), 60));
                $maxHour = max($maxHour, (int) ceil(min(1440, $piece['endMin'] + $piece['travel']) / 60));
                $days[$date]['events'][] = $piece;
            }
        }

        foreach ($days as &$day) {
            $day['events'] = self::assignLanes($day['events']);
        }
        unset($day);

        $summary = [];
        foreach (self::KINDS as $kind => $meta) {
            if (isset($counts[$kind])) {
                $summary[] = F::plural($counts[$kind], strtolower($meta['label']), strtolower($meta['label']));
            }
        }

        return [
            'monday' => $monday,
            'title' => $this->weekTitle($monday),
            'prevWeek' => date('Y-m-d', strtotime("{$monday} -7 days")),
            'nextWeek' => date('Y-m-d', strtotime("{$monday} +7 days")),
            'isCurrentWeek' => self::weekStart($this->today) === $monday,
            'days' => array_values($days),
            'hours' => ['from' => $minHour, 'to' => min(24, $maxHour)],
            'nowMin' => (int) date('G') * 60 + (int) date('i'),
            'people' => $this->peopleList(),
            'summary' => ($summary !== [] ? implode(', ', $summary) : 'Nessun appuntamento')
                . ($awayMinutes > 0 ? ' · circa ' . WorkOrderOverviewService::duration($awayMinutes) . ' fuori sede' : ''),
            'conflicts' => count(array_filter($conflicts)),
        ];
    }

    /** Elenco dei prossimi giorni (vista elenco e telefono). */
    public function agenda(string $from, int $days = 14): array
    {
        $to = date('Y-m-d', strtotime("{$from} +{$days} days"));
        $list = $this->appointments->findBetween("{$from} 00:00:00", "{$to} 00:00:00");
        $conflicts = $this->conflicts($list);

        $grouped = [];
        foreach ($list as $appointment) {
            $date = substr((string) $appointment->startAt, 0, 10);
            $dayIndex = (int) date('N', strtotime($date)) - 1;
            $grouped[$date] ??= [
                'date' => $date,
                'label' => ucfirst(self::DAY_NAMES_LONG[$dayIndex]) . ' ' . F::shortDate($date, $this->today),
                'when' => F::relativeDays($date, $this->today),
                'isToday' => $date === $this->today,
                'events' => [],
            ];
            $grouped[$date]['events'][] = $this->eventItem($appointment, $conflicts[(int) $appointment->id] ?? null);
        }

        return array_values($grouped);
    }

    /** La scheda di un appuntamento. */
    public function detail(Appointment $a): array
    {
        $sameTime = $this->appointments->findBetween((string) $a->startAt, $a->end());
        $conflicts = $this->conflicts(array_merge([$a], array_filter($sameTime, static fn (Appointment $o) => $o->id !== $a->id)));
        $item = $this->eventItem($a, $conflicts[(int) $a->id] ?? null);

        $start = strtotime((string) $a->startAt);
        $end = strtotime($a->end());
        $dayIndex = (int) date('N', $start) - 1;
        $sameDay = date('Y-m-d', $start) === date('Y-m-d', $end);
        $when = ucfirst(self::DAY_NAMES_LONG[$dayIndex]) . ' ' . F::longDate(date('Y-m-d', $start)) . ', ' . date('H:i', $start) . '-'
            . ($sameDay ? date('H:i', $end) : F::shortDate(date('Y-m-d', $end), $this->today) . ' ' . date('H:i', $end));

        $context = null;
        if ($a->entityType === 'work_orders' && $a->entityId !== null && $this->workOrders !== null) {
            $order = $this->workOrders->find((int) $a->entityId);
            if ($order !== null) {
                $context = ['label' => 'Commessa: ' . $order->title, 'href' => '/commesse/' . $order->id];
            }
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $a->checklist)), static fn (string $l) => $l !== ''));

        return $item + [
            'whenLong' => $when,
            'duration' => WorkOrderOverviewService::duration((int) round(($end - $start) / 60)),
            'relative' => F::relativeDays(date('Y-m-d', $start), $this->today),
            'mapsUrl' => $a->location ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($a->location) : null,
            'context' => $context,
            'notes' => $a->notes,
            'checklist' => array_map(static fn (string $l) => [
                'text' => trim(preg_replace('/^\[x\]\s*/i', '', $l)),
                'done' => str_starts_with(strtolower($l), '[x]'),
            ], $lines),
            'google' => $a->googleEventId !== null
                ? ($a->source === 'google' ? 'Creato su Google Calendar' : 'Sul Google Calendar, con gli inviti ai colleghi')
                : null,
        ];
    }

    /**
     * Appuntamenti in breve per altre schede (commessa, cliente), con il
     * giorno scritto per esteso.
     *
     * @param Appointment[] $list
     */
    public function shortList(array $list): array
    {
        return array_map(function (Appointment $a) {
            $date = substr((string) $a->startAt, 0, 10);

            return $this->eventItem($a, null) + [
                'dayLabel' => ucfirst(self::DAY_NAMES_LONG[(int) date('N', strtotime($date)) - 1]) . ' ' . F::shortDate($date, $this->today),
                'past' => $a->end() < date('Y-m-d H:i:s'),
            ];
        }, $list);
    }

    private function eventItem(Appointment $a, ?string $conflict): array
    {
        $kind = isset(self::KINDS[$a->kind]) ? $a->kind : 'altro';
        $meta = self::KINDS[$kind];
        $people = $this->people();
        $customer = $a->customerId !== null && $this->customers !== null ? $this->customers->find((int) $a->customerId) : null;

        return [
            'id' => (int) $a->id,
            'href' => '/appuntamenti/' . $a->id,
            'title' => (string) $a->title,
            'kind' => $kind,
            'kindLabel' => $meta['label'],
            'color' => $meta['color'],
            'away' => $meta['away'],
            'time' => date('H:i', strtotime((string) $a->startAt)) . '-' . date('H:i', strtotime($a->end())),
            'travel' => (int) $a->travelMinutes,
            'location' => $a->location,
            'online' => (int) $a->isOnline === 1,
            'meetUrl' => $a->meetUrl,
            'customer' => $customer?->displayName(),
            'customerHref' => $customer !== null ? '/clienti/' . $customer->id : null,
            'people' => array_values(array_filter(array_map(static fn (int $id) => isset($people[$id]) ? ['id' => $id] + $people[$id] : null, $a->peopleIds()))),
            'stage' => (string) $a->stage,
            'cancelled' => $a->stage === 'annullato',
            'tentative' => $a->stage === 'da confermare',
            'conflict' => $conflict,
            'synced' => $a->googleEventId !== null,
        ];
    }

    /**
     * La stessa persona in due appuntamenti che si sovrappongono (contando
     * il viaggio): la frase da mostrare su ognuno dei due.
     *
     * @param Appointment[] $list
     * @return array<int, string> id appuntamento => "Lena e' anche a «...» 10:00-12:00"
     */
    private function conflicts(array $list): array
    {
        $active = array_values(array_filter($list, static fn (Appointment $a) => $a->isActive()));
        $people = $this->people();
        $result = [];
        foreach ($active as $i => $a) {
            $aStart = strtotime((string) $a->startAt) - 60 * (int) $a->travelMinutes;
            $aEnd = strtotime($a->end()) + 60 * (int) $a->travelMinutes;
            foreach (array_slice($active, $i + 1) as $b) {
                $bStart = strtotime((string) $b->startAt) - 60 * (int) $b->travelMinutes;
                $bEnd = strtotime($b->end()) + 60 * (int) $b->travelMinutes;
                if ($aStart >= $bEnd || $bStart >= $aEnd) {
                    continue;
                }
                $shared = array_intersect($a->peopleIds(), $b->peopleIds());
                if ($shared === []) {
                    continue;
                }
                $names = implode(' e ', array_map(static fn (int $id) => $people[$id]['name'] ?? '?', $shared));
                $verb = count($shared) === 1 ? "e'" : 'sono';
                $result[(int) $a->id] ??= "{$names} {$verb} anche a \u{ab}{$b->title}\u{bb} " . date('H:i', strtotime((string) $b->startAt)) . '-' . date('H:i', strtotime($b->end()));
                $result[(int) $b->id] ??= "{$names} {$verb} anche a \u{ab}{$a->title}\u{bb} " . date('H:i', strtotime((string) $a->startAt)) . '-' . date('H:i', strtotime($a->end()));
            }
        }

        return $result;
    }

    /**
     * Colonne affiancate per gli appuntamenti che si sovrappongono nello
     * stesso giorno (come i calendari veri): ogni gruppo che si tocca
     * divide la larghezza.
     */
    private static function assignLanes(array $events): array
    {
        usort($events, static fn (array $a, array $b) => [$a['startMin'], -$a['endMin']] <=> [$b['startMin'], -$b['endMin']]);

        $result = [];
        $group = [];
        $groupEnd = -1;
        $flush = static function () use (&$group, &$result): void {
            $laneEnds = [];
            foreach ($group as &$event) {
                $lane = 0;
                while (isset($laneEnds[$lane]) && $laneEnds[$lane] > $event['startMin'] - $event['travel']) {
                    $lane++;
                }
                $laneEnds[$lane] = $event['endMin'] + $event['travel'];
                $event['lane'] = $lane;
            }
            unset($event);
            foreach ($group as $event) {
                $result[] = $event + ['lanes' => max(1, count($laneEnds))];
            }
            $group = [];
        };

        foreach ($events as $event) {
            if ($group !== [] && $event['startMin'] - $event['travel'] >= $groupEnd) {
                $flush();
                $groupEnd = -1;
            }
            $group[] = $event;
            $groupEnd = max($groupEnd, $event['endMin'] + $event['travel']);
        }
        if ($group !== []) {
            $flush();
        }

        return $result;
    }

    private function weekTitle(string $monday): string
    {
        $sunday = date('Y-m-d', strtotime("{$monday} +6 days"));
        $sameMonth = date('m', strtotime($monday)) === date('m', strtotime($sunday));

        return $sameMonth
            ? (int) date('j', strtotime($monday)) . '-' . (int) date('j', strtotime($sunday)) . ' ' . F::monthName((int) date('n', strtotime($monday))) . ' ' . date('Y', strtotime($monday))
            : F::shortDate($monday, $sunday) . ' - ' . F::shortDate($sunday, $monday) . ' ' . date('Y', strtotime($sunday));
    }

    /** @return list<array{id:int, name:string, initials:string, color:int}> */
    private function peopleList(): array
    {
        $list = [];
        foreach ($this->people() as $id => $person) {
            $list[] = ['id' => $id] + $person;
        }

        return $list;
    }

    /** @return array<int, array{name:string, initials:string, color:int}> */
    private function people(): array
    {
        if ($this->people === null) {
            $this->people = [];
            $index = 0;
            foreach ($this->users->findAll([], 'first_name') as $user) {
                $name = trim((string) $user->firstName) ?: (string) $user->username;
                $this->people[(int) $user->id] = ['name' => $name, 'initials' => F::initials(trim("{$user->firstName} {$user->lastName}") ?: $name), 'color' => $index++ % 5];
            }
        }

        return $this->people;
    }
}
