<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderTypeMilestone;

final class WorkOrderTypeMilestoneRepository extends AbstractRepository
{
    protected string $table = 'work_order_type_milestones';
    protected string $modelClass = WorkOrderTypeMilestone::class;

    /** @return WorkOrderTypeMilestone[] in ordine */
    public function findByWorkOrderType(int $workOrderTypeId): array
    {
        return $this->findAll(['work_order_type_id' => $workOrderTypeId], 'position, id');
    }
}
