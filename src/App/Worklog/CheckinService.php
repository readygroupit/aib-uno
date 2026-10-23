<?php

declare(strict_types=1);

namespace App\Worklog;

use App\Core\Container;
use App\Model\Task;
use App\Model\WorkSchedule;
use App\Package\PackageCatalogService;
use App\Planner\DayPlanner;
use App\Repository\AppointmentRepository;
use App\Repository\TaskRepository;
use App\Repository\WorkCheckinRepository;
use App\Repository\WorkEntryRepository;
use App\Repository\WorkOrderRepository;
use App\Support\ItalianFormat as F;
use App\Work\TaskService;
use App\Work\WorkOrderOverviewService;
use App\Work\WorkOrderService;

/**
 * "Cosa hai fatto?" due volte al giorno: alla pausa (la mattina) e a fine
 * giornata (il pomeriggio). Si propone quello che il piano prevedeva in
 * quel pezzo di giornata, le attivita' gia' spuntate senza tempo e gli
 * appuntamenti: si conferma con un tocco e si corregge la durata.
 *
 * Le voci finiscono in work_entries e il tempo impiegato di ogni attivita'
 * e' la loro somma; "fatto" chiude anche l'attivita' (con tutto quello che
 * ne segue: commessa, ripianificazione, ricorrenze).
 */
final class CheckinService
{
    private const LABELS = ['mattina' => 'stamattina', 'pomeriggio' => 'oggi pomeriggio', 'giornata' => 'oggi'];

    private DayPlanner $planner;
    private WorkEntryRepository $entries;
    private WorkCheckinRepository $checkins;
    private TaskRepository $tasks;
    private TaskService $taskService;
    private ?AppointmentRepository $appointments = null;
    private ?WorkOrderRepository $orders = null;
    private string $today;

    public function __construct(Container $container)
    {
        $this->planner = $container->get(DayPlanner::class);
        $this->entries = $container->get(WorkEntryRepository::class);
        $this->checkins = $container->get(WorkCheckinRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
        $this->taskService = $container->get(TaskService::class);
        $installed = PackageCatalogService::installedNames();
        if (in_array('appointments', $installed, true)) {
            $this->appointments = $container->get(AppointmentRepository::class);
        }
        if (in_array('work_orders', $installed, true)) {
            $this->orders = $container->get(WorkOrderRepository::class);
        }
        $this->today = date('Y-m-d');
    }

    /**
     * Il momento di chiedere: l'ultimo punto di controllo passato (pausa o
     * fine giornata, oggi o l'ultimo giorno di lavoro) senza risposta.
     *
     * @return ?array{date:string, period:string, label:string, question:string}
     */
    public function due(int $userId): ?array
    {
        $schedule = $this->planner->schedule($userId);
        $now = time();
        $last = $this->checkins->lastForUser($userId);
        $lastAt = $last !== null ? strtotime((string) $last->checkedAt) : 0;

        $points = [];
        $day = $this->today;
        for ($i = 0; $i < 5 && count($points) < 4; $i++) {
            if ($schedule->worksOn($day)) {
                foreach (array_reverse($this->periods($schedule)) as $period) {
                    $points[] = ['date' => $day, 'period' => $period['period'], 'at' => strtotime($day) + 60 * $period['askAt']];
                }
            }
            $day = date('Y-m-d', strtotime("{$day} -1 day"));
        }

        foreach ($points as $index => $point) {
            if ($point['at'] > $now) {
                continue;
            }
            // Gia' risposto dopo (o poco prima di) questo momento.
            if ($lastAt >= $point['at'] - 45 * 60) {
                return null;
            }
            // Saltata anche la mattina dello stesso giorno: si chiede la giornata intera.
            $previous = $points[$index + 1] ?? null;
            if ($point['period'] === 'pomeriggio' && $previous !== null && $previous['date'] === $point['date'] && $lastAt < $previous['at'] - 45 * 60) {
                $point['period'] = 'giornata';
            }
            $when = $point['date'] === $this->today
                ? self::LABELS[$point['period']]
                : ($point['period'] === 'pomeriggio' ? 'il pomeriggio di ' : ($point['period'] === 'mattina' ? 'la mattina di ' : '')) . $this->planner->dayName($point['date']);

            return [
                'date' => $point['date'],
                'period' => $point['period'],
                'label' => $when,
                'question' => "Cosa hai fatto {$when}?",
            ];
        }

        return null;
    }

    /**
     * Le righe da confermare per quel pezzo di giornata.
     *
     * @return list<array>
     */
    public function proposals(int $userId, string $date, string $period): array
    {
        $schedule = $this->planner->schedule($userId);
        $range = $this->range($schedule, $period);
        $logged = $this->entries->taskIdsLogged($userId, $date);
        $rows = [];
        $seen = [];

        // Gli appuntamenti di quel pezzo di giornata: il tempo e' gia' noto.
        foreach ($this->planner->appointmentsFor($userId, $date) as $a) {
            $start = (int) date('G', strtotime((string) $a->startAt)) * 60 + (int) date('i', strtotime((string) $a->startAt));
            if ($start < $range[0] || $start >= $range[1]) {
                continue;
            }
            $minutes = (int) round((strtotime($a->end()) - strtotime((string) $a->startAt)) / 60) + 2 * (int) $a->travelMinutes;
            $rows[] = [
                'key' => 'a' . $a->id,
                'kind' => 'appointment',
                'title' => (string) $a->title,
                'context' => 'Appuntamento ' . date('H:i', strtotime((string) $a->startAt)) . ((int) $a->travelMinutes > 0 ? ', viaggio compreso' : ''),
                'minutes' => $minutes,
                'state' => 'fatto',
                'taskId' => null,
                'customerId' => $a->customerId,
            ];
        }

        // Attivita' gia' spuntate quel giorno ma senza tempo segnato.
        foreach ($this->tasks->findForBoard($userId, "{$date} 00:00:00") as $task) {
            if ($task->stage === 'fatta' && substr((string) $task->completedAt, 0, 10) === $date && !in_array((int) $task->id, $logged, true)) {
                $rows[] = $this->taskRow($task, 'fatto', (int) ($task->estimatedMinutes ?: 30));
                $seen[(int) $task->id] = true;
            }
        }

        // Quello che il piano prevedeva in quel pezzo di giornata (piano
        // intero, come se fosse mattina presto).
        $plan = $this->planner->plan($userId, $date, $range[0]);
        foreach ($plan['placed'] as $item) {
            if ($item['kind'] !== 'task' || isset($seen[$item['id']]) || in_array($item['id'], $logged, true)) {
                continue;
            }
            if ($item['start'] >= $range[1] || $item['end'] <= $range[0]) {
                continue;
            }
            $task = $this->tasks->find((int) $item['id']);
            if ($task instanceof Task) {
                $rows[] = $this->taskRow($task, null, $item['end'] - $item['start']);
                $seen[$item['id']] = true;
            }
        }

        return $rows;
    }

    /**
     * Salva le risposte: una voce di registro per ogni riga confermata,
     * attivita' chiuse se "fatto", tempo impiegato aggiornato.
     *
     * @param list<array{taskId:?int, title:string, minutes:int, state:string, customerId:?int}> $rows
     * @return array{entries:int, minutes:int, closed:int}
     */
    public function save(int $userId, string $date, string $period, array $rows): array
    {
        $count = 0;
        $total = 0;
        $closed = 0;
        foreach ($rows as $row) {
            $minutes = max(0, min(720, (int) $row['minutes']));
            $state = (string) $row['state'];
            if ($minutes === 0 || !in_array($state, ['fatto', 'in parte'], true)) {
                continue;
            }
            $taskId = $row['taskId'] !== null ? (int) $row['taskId'] : null;
            $this->entries->insert([
                'user_id' => $userId,
                'work_date' => $date,
                'minutes' => $minutes,
                'title' => mb_substr(trim((string) $row['title']) ?: 'Lavoro', 0, 200),
                'task_id' => $taskId,
                'customer_id' => $row['customerId'] ?? null,
                'source' => 'check-in',
            ]);
            $count++;
            $total += $minutes;

            if ($taskId !== null) {
                $task = $this->tasks->find($taskId);
                if ($task instanceof Task) {
                    if ($state === 'fatto' && $task->isOpen()) {
                        $this->taskService->complete($taskId);
                        $closed++;
                    } elseif ($state === 'in parte' && $task->stage === 'da fare') {
                        $this->tasks->update($taskId, ['stage' => 'in corso']);
                    }
                    $this->tasks->update($taskId, ['actual_minutes' => $this->entries->minutesForTask($taskId)]);
                }
            }
        }

        $this->checkins->insert(['user_id' => $userId, 'checked_at' => date('Y-m-d H:i:s'), 'period' => $period]);

        return ['entries' => $count, 'minutes' => $total, 'closed' => $closed];
    }

    /** @return list<array{period:string, askAt:int}> pezzi di giornata e quando chiedere */
    private function periods(WorkSchedule $schedule): array
    {
        $end = WorkSchedule::minutes($schedule->endTime, 1080);
        $breakStart = WorkSchedule::minutes($schedule->breakStart, -1);
        if ($breakStart < 0) {
            return [['period' => 'giornata', 'askAt' => $end - 30]];
        }

        return [
            ['period' => 'mattina', 'askAt' => $breakStart],
            ['period' => 'pomeriggio', 'askAt' => $end - 30],
        ];
    }

    /** @return array{0:int, 1:int} minuti di inizio e fine del pezzo di giornata */
    private function range(WorkSchedule $schedule, string $period): array
    {
        $start = WorkSchedule::minutes($schedule->startTime, 540);
        $end = WorkSchedule::minutes($schedule->endTime, 1080);
        $breakStart = WorkSchedule::minutes($schedule->breakStart, -1);
        $breakEnd = WorkSchedule::minutes($schedule->breakEnd, -1);

        return match ($period) {
            'mattina' => [$start, $breakStart > 0 ? $breakStart : $end],
            'pomeriggio' => [$breakEnd > 0 ? $breakEnd : $start, $end],
            default => [$start, $end],
        };
    }

    private function taskRow(Task $task, ?string $state, int $minutes): array
    {
        $context = null;
        $customerId = null;
        if ($task->entityType === WorkOrderService::ENTITY && $task->entityId !== null && $this->orders !== null) {
            $order = $this->orders->find((int) $task->entityId);
            $context = $order?->title;
            $customerId = $order?->customerId;
        } elseif ($task->entityType === 'customers') {
            $customerId = $task->entityId;
            $context = $this->planner->customerName((int) $task->entityId);
        }

        return [
            'key' => 't' . $task->id,
            'kind' => 'task',
            'title' => (string) $task->title,
            'context' => $context,
            'minutes' => max(15, $minutes),
            'state' => $state,
            'taskId' => (int) $task->id,
            'customerId' => $customerId,
            'estimate' => $task->estimatedMinutes ? WorkOrderOverviewService::duration((int) $task->estimatedMinutes) : null,
        ];
    }
}
