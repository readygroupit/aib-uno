<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderType;

final class WorkOrderTypeRepository extends AbstractRepository
{
    protected string $table = 'work_order_types';
    protected string $modelClass = WorkOrderType::class;
}
