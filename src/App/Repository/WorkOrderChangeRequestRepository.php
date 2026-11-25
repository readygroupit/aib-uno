<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderChangeRequest;

final class WorkOrderChangeRequestRepository extends AbstractRepository
{
    protected string $table = 'work_order_change_requests';
    protected string $modelClass = WorkOrderChangeRequest::class;

    /** @return WorkOrderChangeRequest[] in ordine */
    public function findByWorkOrder(int $workOrderId): array
    {
        return $this->findAll(['work_order_id' => $workOrderId], 'requested_at DESC, id DESC');
    }
}
