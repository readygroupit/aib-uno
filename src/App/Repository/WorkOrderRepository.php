<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrder;

final class WorkOrderRepository extends AbstractRepository
{
    protected string $table = 'work_orders';
    protected string $modelClass = WorkOrder::class;

    /** @return WorkOrder[] aperte, piu' quelle consegnate da $deliveredSince in poi */
    public function findForBoard(string $deliveredSince): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM work_orders
             WHERE status != 0
               AND (stage IN ('da avviare', 'in corso', 'in attesa del cliente', 'in pausa')
                    OR (stage = 'consegnata' AND (delivered_at IS NULL OR delivered_at >= :since)))
             ORDER BY FIELD(priority, 'alta', 'normale', 'bassa'), due_date IS NULL, due_date, id",
            ['since' => $deliveredSince]
        );

        return array_map(static fn (array $row) => WorkOrder::fromArray($row), $rows);
    }

    /** @return WorkOrder[] */
    public function findByCustomer(int $customerId): array
    {
        return $this->findAll(
            ['customer_id' => $customerId],
            "FIELD(stage, 'in attesa del cliente', 'in corso', 'da avviare', 'in pausa', 'consegnata', 'annullata'), due_date"
        );
    }

    /** @return array<int,string> id => titolo */
    public function titlesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll("SELECT id, title FROM work_orders WHERE id IN ({$placeholders})", $ids);

        return array_column($rows, 'title', 'id');
    }

    /** @return array<int,int> id commessa => id cliente */
    public function customersByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll("SELECT id, customer_id FROM work_orders WHERE id IN ({$placeholders})", $ids);

        return array_map('intval', array_column($rows, 'customer_id', 'id'));
    }
}
