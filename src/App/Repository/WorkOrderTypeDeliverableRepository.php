<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderTypeDeliverable;

final class WorkOrderTypeDeliverableRepository extends AbstractRepository
{
    protected string $table = 'work_order_type_deliverables';
    protected string $modelClass = WorkOrderTypeDeliverable::class;

    /** @return WorkOrderTypeDeliverable[] in ordine */
    public function findByWorkOrderType(int $workOrderTypeId): array
    {
        return $this->findAll(['work_order_type_id' => $workOrderTypeId], 'position, id');
    }
}
