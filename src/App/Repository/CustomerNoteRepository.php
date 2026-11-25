<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CustomerNote;

final class CustomerNoteRepository extends AbstractRepository
{
    protected string $table = 'customer_notes';
    protected string $modelClass = CustomerNote::class;

    /** @return CustomerNote[] le piu' recenti prima */
    public function findByCustomer(int $customerId, int $limit = 50): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM customer_notes WHERE status != 0 AND customer_id = :c ORDER BY noted_at DESC, id DESC LIMIT ' . max(1, $limit),
            ['c' => $customerId]
        );

        return array_map(static fn (array $row) => CustomerNote::fromArray($row), $rows);
    }

    /** L'ultima nota di un tipo per un progetto (es. le informazioni per il preventivo). */
    public function findLatestForWorkOrder(int $workOrderId, string $kind): ?CustomerNote
    {
        $row = $this->db->fetchRow(
            'SELECT * FROM customer_notes WHERE status != 0 AND work_order_id = :w AND kind = :k ORDER BY noted_at DESC, id DESC LIMIT 1',
            ['w' => $workOrderId, 'k' => $kind]
        );

        return $row ? CustomerNote::fromArray($row) : null;
    }

    /** Le idee della roadmap diventate un progetto (ancora aperte). @return CustomerNote[] */
    public function openRoadmapForWorkOrder(int $workOrderId): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM customer_notes WHERE status != 0 AND on_roadmap = 1 AND work_order_id = :w AND (roadmap_status IS NULL OR roadmap_status NOT IN ('fatta', 'scartata'))", ['w' => $workOrderId]);

        return array_map(static fn (array $row) => CustomerNote::fromArray($row), $rows);
    }
}
