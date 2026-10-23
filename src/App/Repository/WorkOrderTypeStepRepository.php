<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderTypeStep;

final class WorkOrderTypeStepRepository extends AbstractRepository
{
    protected string $table = 'work_order_type_steps';
    protected string $modelClass = WorkOrderTypeStep::class;

    /** @return WorkOrderTypeStep[] in ordine */
    public function findByType(int $typeId): array
    {
        return $this->findAll(['work_order_type_id' => $typeId], 'position, id');
    }

    /** @return array<int,int> id tipo => numero di fasi */
    public function countByType(): array
    {
        $rows = $this->db->fetchAll('SELECT work_order_type_id AS t, COUNT(*) AS n FROM work_order_type_steps WHERE status != 0 GROUP BY work_order_type_id');

        return array_map('intval', array_column($rows, 'n', 't'));
    }
}
