<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderDeliverable;

final class WorkOrderDeliverableRepository extends AbstractRepository
{
    protected string $table = 'work_order_deliverables';
    protected string $modelClass = WorkOrderDeliverable::class;

    /** @return WorkOrderDeliverable[] in ordine */
    public function findByWorkOrder(int $workOrderId): array
    {
        return $this->findAll(['work_order_id' => $workOrderId], 'position, id');
    }
}
