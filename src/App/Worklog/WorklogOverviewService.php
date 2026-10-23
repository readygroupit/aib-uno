<?php

declare(strict_types=1);

namespace App\Worklog;

use App\Core\Container;
use App\Model\WorkEntry;
use App\Package\PackageCatalogService;
use App\Planner\DayPlanner;
use App\Planner\PlannerOverviewService;
use App\Repository\CustomerRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkCheckinRepository;
use App\Repository\WorkEntryRepository;
use App\Support\ItalianFormat as F;
use App\Work\WorkOrderOverviewService;

/**
 * Il registro visto da due lati:
 * - la persona: la sua settimana, giorno per giorno, con le voci;
 * - i titolari: ore segnate da ognuno, chi oggi non ha ancora risposto,
 *   tempi medi per tipo di lavoro con lo scarto dal previsto, i casi da
 *   guardare, le attivita' ferme da tempo, chi oggi e' oltre il carico.
 */
final class WorklogOverviewService
{
    private WorkEntryRepository $entries;
    private WorkCheckinRepository $checkins;
    private TaskRepository $tasks;
    private UserRepository $users;
    private DurationStats $stats;
    private DayPlanner $planner;
    private PlannerOverviewService $plannerOverview;
    private ?CustomerRepository $customers = null;
    private ?WorkOrderOverviewService $orders = null;
    private string $today;

    public function __construct(Container $container)
    {
        $this->entries = $container->get(WorkEntryRepository::class);
        $this->checkins = $container->get(WorkCheckinRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
        $this->users = $container->get(UserRepository::class);
        $this->stats = $container->get(DurationStats::class);
        $this->planner = $container->get(DayPlanner::class);
        $this->plannerOverview = $container->get(PlannerOverviewService::class);
        $installed = PackageCatalogService::installedNames();
        if (in_array('customers', $installed, true)) {
            $this->customers = $container->get(CustomerRepository::class);
        }
        if (in_array('work_orders', $installed, true)) {
            $this->orders = $container->get(WorkOrderOverviewService::class);
        }
        $this->today = date('Y-m-d');
    }

    /** La settimana di una persona nel registro. */
    public function myWeek(int $userId, string $monday): array
    {
        $friday = date('Y-m-d', strtotime("{$monday} +6 days"));
        $byDay = [];
        foreach ($this->entries->findForUserBetween($userId, $monday, $friday) as $entry) {
            $byDay[(string) $entry->workDate][] = $entry;
        }

        $days = [];
        $total = 0;
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime("{$monday} +{$i} days"));
            $list = $byDay[$date] ?? [];
            if ($i >= 5 && $list === []) {
                continue;
            }
            $minutes = array_sum(array_map(static fn (WorkEntry $e) => (int) $e->minutes, $list));
            $total += $minutes;
            $days[] = [
                'date' => $date,
                'label' => ucfirst($this->planner->dayName($date)),
                'isToday' => $date === $this->today,
                'future' => $date > $this->today,
                'total' => $minutes > 0 ? WorkOrderOverviewService::duration($minutes) : null,
                'entries' => array_map(fn (WorkEntry $e) => [
                    'title' => (string) $e->title,
                    'customer' => $e->customerId !== null ? $this->customers?->find((int) $e->customerId)?->displayName() : null,
                    'duration' => WorkOrderOverviewService::duration((int) $e->minutes),
                    'href' => '/registro/voci/' . $e->id,
                    'source' => $e->source,
                ], $list),
            ];
        }

        return [
            'days' => $days,
            'total' => WorkOrderOverviewService::duration($total),
            'totalMinutes' => $total,
        ];
    }

    /** Il pannello dei titolari. */
    public function insights(): array
    {
        $monday = date('Y-m-d', strtotime('monday this week'));
        $minutes = $this->entries->minutesPerUserBetween($monday, $this->today);
        $checkedToday = $this->checkins->usersCheckedOn($this->today);
        $team = $this->plannerOverview->team($this->today);

        $people = [];
        foreach ($this->users->findAll([], 'first_name') as $user) {
            $id = (int) $user->id;
            $name = trim((string) $user->firstName) ?: (string) $user->username;
            $schedule = $this->planner->schedule($id);
            $firstAsk = strtotime($this->today) + 60 * \App\Model\WorkSchedule::minutes($schedule->breakStart ?: $schedule->endTime, 780);
            $people[] = [
                'name' => $name,
                'initials' => F::initials(trim("{$user->firstName} {$user->lastName}") ?: $name),
                'week' => WorkOrderOverviewService::duration($minutes[$id] ?? 0),
                'weekMinutes' => $minutes[$id] ?? 0,
                // "Non ha ancora segnato" solo dopo il primo momento in cui gli e' stato chiesto.
                'missing' => $schedule->worksOn($this->today) && time() >= $firstAsk && !in_array($id, $checkedToday, true),
            ];
        }

        // Commesse ferme, poi attivita' rimaste indietro da piu' di 5 giorni.
        // Per le fasi conta il giorno "vero" (una commessa che aspetta il
        // cliente non ha fasi ferme per colpa nostra) e una commessa gia'
        // segnalata come ferma non si ripete fase per fase.
        $staleOrders = [];
        $staleOrderIds = [];
        if ($this->orders !== null) {
            foreach ($this->orders->board()['columns'] as $column) {
                foreach ($column['cards'] as $card) {
                    if ($card['flags']['stale']) {
                        $staleOrders[] = ['title' => $card['title'] . ' · ' . $card['customer'], 'href' => $card['href'], 'when' => "nessun movimento da {$card['idleDays']} giorni"];
                        $staleOrderIds[] = $card['id'];
                    }
                }
            }
        }
        $openTasks = array_filter($this->tasks->findForBoard(null, $this->today . ' 00:00:00'), static fn ($t) => $t->isOpen());
        $effective = [];
        if ($this->orders !== null) {
            $orderIds = array_map(static fn ($t) => $t->entityId, array_filter($openTasks, static fn ($t) => $t->entityType === 'work_orders'));
            foreach ($this->tasks->findForEntities('work_orders', $orderIds) as $orderTasks) {
                $effective += $this->orders->effectiveDays($orderTasks);
            }
        }
        $stuck = [];
        foreach ($openTasks as $task) {
            $day = $effective[(int) $task->id] ?? $task->day();
            if ($day === null || (int) $task->waitsForClient === 1 || F::daysFrom($day, $this->today) > -5
                || ($task->entityType === 'work_orders' && in_array((int) $task->entityId, $staleOrderIds, true))) {
                continue;
            }
            $stuck[] = ['title' => (string) $task->title, 'href' => '/attivita/' . $task->id, 'when' => 'ferma da ' . F::plural(-F::daysFrom($day, $this->today), 'giorno', 'giorni')];
        }

        $overloaded = array_values(array_filter($team['people'], static fn (array $p) => $p['working'] && $p['load'] > 105));

        return [
            'people' => $people,
            'weekMinutes' => array_sum(array_column($people, 'weekMinutes')),
            'missing' => array_values(array_filter($people, static fn (array $p) => $p['missing'])),
            'report' => $this->stats->report(),
            'anomalies' => $this->stats->anomalies(),
            'stuck' => array_merge($staleOrders, $stuck),
            'overloaded' => array_map(static fn (array $p) => ['name' => $p['name'], 'load' => $p['load'], 'overflow' => $p['overflowLabel'], 'href' => $p['href']], $overloaded),
        ];
    }
}
