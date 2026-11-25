<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderMilestone;

final class WorkOrderMilestoneRepository extends AbstractRepository
{
    protected string $table = 'work_order_milestones';
    protected string $modelClass = WorkOrderMilestone::class;

    /** @return WorkOrderMilestone[] in ordine */
    public function findByWorkOrder(int $workOrderId): array
    {
        return $this->findAll(['work_order_id' => $workOrderId], 'position, id');
    }

    /** @return array<int, WorkOrderMilestone[]> id progetto => righe */
    public function findForOrders(array $orderIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll("SELECT * FROM {$this->table} WHERE status != 0 AND work_order_id IN ({$placeholders}) ORDER BY work_order_id, id", $ids);
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['work_order_id']][] = WorkOrderMilestone::fromArray($row);
        }

        return $result;
    }
}
