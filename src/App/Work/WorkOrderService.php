<?php

declare(strict_types=1);

namespace App\Work;

use App\Core\Container;
use App\Model\Task;
use App\Model\WorkOrder;
use App\Model\WorkOrderType;
use App\Repository\TaskRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\WorkOrderTypeStepRepository;

/**
 * Cosa succede a una commessa senza che nessuno debba deciderlo: le fasi
 * nascono dal tipo di commessa, lo stato segue le fasi (in corso appena se
 * ne chiude una, "in attesa del cliente" quando tocca a una fase che
 * aspetta il cliente, di nuovo in corso quando arriva), l'ultimo movimento
 * si aggiorna da solo. Le scelte vere (pausa, consegna, annullamento)
 * restano all'operatore.
 */
final class WorkOrderService
{
    public const ENTITY = 'work_orders';

    /** Una commessa in corso senza movimenti da tanti giorni e' "ferma". */
    public const STALE_DAYS = 7;

    private WorkOrderRepository $orders;
    private WorkOrderTypeRepository $types;
    private WorkOrderTypeStepRepository $steps;
    private TaskRepository $tasks;

    public function __construct(Container $container)
    {
        $this->orders = $container->get(WorkOrderRepository::class);
        $this->types = $container->get(WorkOrderTypeRepository::class);
        $this->steps = $container->get(WorkOrderTypeStepRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
    }

    /**
     * Le fasi del tipo di commessa diventano attivita', con il giorno
     * previsto calcolato dalla data di inizio. Solo se la commessa non ne
     * ha ancora: non duplica mai.
     *
     * @return int attivita' create
     */
    public function createStepsFromType(int $workOrderId): int
    {
        $order = $this->orders->find($workOrderId);
        if (!$order instanceof WorkOrder || $order->workOrderTypeId === null) {
            return 0;
        }
        if ($this->tasks->findForEntity(self::ENTITY, $workOrderId) !== []) {
            return 0;
        }

        $type = $this->types->find((int) $order->workOrderTypeId);
        $steps = $this->steps->findByType((int) $order->workOrderTypeId);
        $start = $order->startDate ?? date('Y-m-d');

        $lastOffset = 0;
        foreach ($steps as $index => $step) {
            $offset = (int) ($step->offsetDays ?? 0);
            $lastOffset = max($lastOffset, $offset);
            $this->tasks->insert([
                'title' => $step->title,
                'description' => $step->description,
                'checklist' => $step->checklist,
                'position' => $step->position ?? ($index + 1),
                'planned_date' => Workdays::adjust(date('Y-m-d', strtotime("{$start} +{$offset} days"))),
                'estimated_minutes' => $step->estimatedMinutes,
                'waits_for_client' => (int) ($step->waitsForClient ?? 0),
                'assigned_user_id' => $order->ownerUserId,
                'priority' => $order->priority ?? 'normale',
                'stage' => 'da fare',
                'entity_type' => self::ENTITY,
                'entity_id' => $workOrderId,
                'source_step_id' => $step->id,
            ]);
        }

        if ($order->dueDate === null) {
            $days = $type instanceof WorkOrderType && $type->defaultDays !== null ? (int) $type->defaultDays : $lastOffset + 2;
            $this->orders->update($workOrderId, ['due_date' => Workdays::adjust(date('Y-m-d', strtotime("{$start} +{$days} days")))]);
        }

        $this->refresh($workOrderId);

        return count($steps);
    }

    /**
     * Ricalcola lo stato dalle fasi. Non tocca una commessa in pausa,
     * consegnata o annullata: quelle sono decisioni dell'operatore.
     */
    public function refresh(int $workOrderId, bool $touched = true): void
    {
        $order = $this->orders->find($workOrderId);
        if (!$order instanceof WorkOrder) {
            return;
        }

        $data = $touched ? ['last_activity_at' => date('Y-m-d H:i:s')] : [];

        if (in_array($order->stage, ['da avviare', 'in corso', 'in attesa del cliente'], true)) {
            $tasks = $this->tasks->findForEntity(self::ENTITY, $workOrderId);
            $next = $this->nextStep($tasks);
            $anyDone = array_filter($tasks, static fn (Task $t) => $t->stage === 'fatta') !== [];

            if ($next !== null && (int) $next->waitsForClient === 1) {
                $sameWait = $order->stage === 'in attesa del cliente' && $order->waitingReason === $next->title;
                $data += [
                    'stage' => 'in attesa del cliente',
                    'waiting_reason' => $next->title,
                    'waiting_since' => $sameWait && $order->waitingSince !== null ? $order->waitingSince : date('Y-m-d'),
                ];
            } elseif ($order->stage === 'in attesa del cliente' || ($order->stage === 'da avviare' && $anyDone)) {
                $data += ['stage' => 'in corso', 'waiting_reason' => null, 'waiting_since' => null];
            }
        }

        if ($data !== []) {
            $this->orders->update($workOrderId, $data);
        }
    }

    /**
     * Dopo una fase chiusa: se la prossima risulta gia' nel passato (il
     * cliente ci ha messo tanto, o la fase prima e' durata di piu'), le
     * fasi rimaste slittano tutte insieme in modo che la prossima sia oggi,
     * mantenendo gli intervalli tra una e l'altra. La consegna prevista non
     * cambia: se la si supera, la commessa risulta in ritardo.
     *
     * @return int giorni di slittamento (0 se non serviva)
     */
    public function replan(int $workOrderId): int
    {
        $open = array_values(array_filter(
            $this->tasks->findForEntity(self::ENTITY, $workOrderId),
            static fn (Task $t) => $t->isOpen() && $t->plannedDate !== null
        ));
        if ($open === [] || $open[0]->plannedDate >= date('Y-m-d')) {
            return 0;
        }

        $delta = (int) round((strtotime(date('Y-m-d')) - strtotime((string) $open[0]->plannedDate)) / 86400);
        foreach ($open as $task) {
            $this->tasks->update((int) $task->id, ['planned_date' => Workdays::adjust(date('Y-m-d', strtotime("{$task->plannedDate} +{$delta} days")))]);
        }

        return $delta;
    }

    /** @param Task[] $tasks @return ?Task la prima fase ancora da fare, nell'ordine */
    public function nextStep(array $tasks): ?Task
    {
        foreach ($tasks as $task) {
            if ($task->isOpen()) {
                return $task;
            }
        }

        return null;
    }

    /** "In attesa del cliente" scelto a mano, con cosa si aspetta. */
    public function setWaiting(int $workOrderId, string $reason): void
    {
        $this->orders->update($workOrderId, [
            'stage' => 'in attesa del cliente',
            'waiting_reason' => $reason !== '' ? $reason : 'una risposta dal cliente',
            'waiting_since' => date('Y-m-d'),
            'last_activity_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function resume(int $workOrderId): void
    {
        $this->orders->update($workOrderId, [
            'stage' => 'in corso',
            'waiting_reason' => null,
            'waiting_since' => null,
            'last_activity_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function pause(int $workOrderId): void
    {
        $this->orders->update($workOrderId, ['stage' => 'in pausa', 'last_activity_at' => date('Y-m-d H:i:s')]);
    }

    public function deliver(int $workOrderId): void
    {
        $this->orders->update($workOrderId, [
            'stage' => 'consegnata',
            'delivered_at' => date('Y-m-d'),
            'waiting_reason' => null,
            'waiting_since' => null,
            'last_activity_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Ricorda solo quando il cliente e' stato sollecitato: il messaggio lo manda ancora l'operatore. */
    public function markReminded(int $workOrderId): void
    {
        $this->orders->update($workOrderId, ['last_reminder_at' => date('Y-m-d H:i:s')]);
    }
}
