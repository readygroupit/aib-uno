<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderTypeRole;

final class WorkOrderTypeRoleRepository extends AbstractRepository
{
    protected string $table = 'work_order_type_roles';
    protected string $modelClass = WorkOrderTypeRole::class;

    /** @return WorkOrderTypeRole[] in ordine */
    public function findByWorkOrderType(int $workOrderTypeId): array
    {
        return $this->findAll(['work_order_type_id' => $workOrderTypeId], 'position, id');
    }
}
