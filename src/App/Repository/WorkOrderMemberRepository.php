<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderMember;

final class WorkOrderMemberRepository extends AbstractRepository
{
    protected string $table = 'work_order_members';
    protected string $modelClass = WorkOrderMember::class;

    /** @return WorkOrderMember[] in ordine */
    public function findByWorkOrder(int $workOrderId): array
    {
        return $this->findAll(['work_order_id' => $workOrderId], 'id');
    }

    /** @return int[] i clienti dei progetti di cui la persona e' in squadra (non chiusi) */
    public function customerIdsForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT o.customer_id FROM work_order_members m
             JOIN work_orders o ON o.id = m.work_order_id AND o.status != 0
             WHERE m.status != 0 AND m.user_id = :u AND o.customer_id IS NOT NULL
               AND o.stage NOT IN ('consegnata', 'non acquisita', 'annullata')",
            ['u' => $userId]
        );

        return array_map(static fn (array $r) => (int) $r['customer_id'], $rows);
    }

    /** @return array<int, WorkOrderMember[]> id progetto => righe */
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
            $result[(int) $row['work_order_id']][] = WorkOrderMember::fromArray($row);
        }

        return $result;
    }
}
