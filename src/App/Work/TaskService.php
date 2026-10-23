<?php

declare(strict_types=1);

namespace App\Work;

use App\Core\Container;
use App\Model\Task;
use App\Package\PackageCatalogService;
use App\Repository\TaskRepository;

/**
 * Fare e disfare un'attivita': completata (con il tempo impiegato, se lo
 * si dice), riaperta, la prossima di una ricorrente, e la commessa a cui
 * appartiene che si aggiorna di conseguenza.
 */
final class TaskService
{
    private const INTERVALS = ['ogni giorno' => '+1 day', 'ogni settimana' => '+1 week', 'ogni mese' => '+1 month'];

    private TaskRepository $tasks;
    private ?WorkOrderService $workOrders = null;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->tasks = $container->get(TaskRepository::class);
        if (in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            $this->workOrders = $container->get(WorkOrderService::class);
        }
    }

    /** @return ?Task la prossima occorrenza creata, per un'attivita' che si ripete */
    public function complete(int $taskId, ?int $actualMinutes = null): ?Task
    {
        $task = $this->tasks->find($taskId);
        if (!$task instanceof Task) {
            return null;
        }

        $data = ['stage' => 'fatta', 'completed_at' => date('Y-m-d H:i:s')];
        if ($actualMinutes !== null && $actualMinutes > 0) {
            $data['actual_minutes'] = $actualMinutes;
        }
        $this->tasks->update($taskId, $data);

        $next = $this->createNextOccurrence($task);
        if ($this->workOrders !== null && $task->entityType === WorkOrderService::ENTITY && $task->entityId !== null) {
            $this->workOrders->replan((int) $task->entityId);
        }
        $this->afterChange($task);
        // L'attivita' di una richiesta del cliente fatta: e' fatta anche la richiesta.
        if ($task->entityType === 'client_requests' && $task->entityId !== null && in_array('client_requests', PackageCatalogService::installedNames(), true)) {
            $this->container->get(\App\ClientRequests\ClientRequestService::class)->markDoneFromTask((int) $task->entityId);
        }

        return $next;
    }

    public function reopen(int $taskId): void
    {
        $task = $this->tasks->find($taskId);
        if (!$task instanceof Task) {
            return;
        }
        $this->tasks->update($taskId, ['stage' => 'da fare', 'completed_at' => null]);
        $this->afterChange($task);
    }

    /** Dopo un salvataggio dal form: la commessa collegata si riallinea. */
    public function afterChange(Task $task): void
    {
        if ($this->workOrders !== null && $task->entityType === WorkOrderService::ENTITY && $task->entityId !== null) {
            $this->workOrders->refresh((int) $task->entityId);
        }
    }

    private function createNextOccurrence(Task $task): ?Task
    {
        $interval = self::INTERVALS[$task->recurrence] ?? null;
        if ($interval === null) {
            return null;
        }

        $base = $task->day() ?? date('Y-m-d');
        $nextDay = date('Y-m-d', strtotime("{$base} {$interval}"));
        // Una ricorrente rimasta indietro non genera arretrati: riparte da oggi.
        while ($nextDay < date('Y-m-d')) {
            $nextDay = date('Y-m-d', strtotime("{$nextDay} {$interval}"));
        }
        $nextDay = Workdays::adjust($nextDay);
        $dueAt = null;
        if ($task->dueAt !== null) {
            $shift = strtotime($nextDay) - strtotime((string) $task->day());
            $dueAt = date('Y-m-d H:i:s', strtotime($task->dueAt) + $shift);
        }

        $id = $this->tasks->insert([
            'title' => $task->title,
            'description' => $task->description,
            'checklist' => $task->checklist !== null ? preg_replace('/^\s*\[x\]\s*/mi', '', $task->checklist) : null,
            'planned_date' => $nextDay,
            'due_at' => $dueAt,
            'priority' => $task->priority,
            'estimated_minutes' => $task->estimatedMinutes,
            'assigned_user_id' => $task->assignedUserId,
            'recurrence' => $task->recurrence,
            'stage' => 'da fare',
            'entity_type' => $task->entityType,
            'entity_id' => $task->entityId,
        ]);

        return $this->tasks->find($id);
    }
}
