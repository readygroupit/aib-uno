<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkOrderType;

final class WorkOrderTypeRepository extends AbstractRepository
{
    protected string $table = 'work_order_types';
    protected string $modelClass = WorkOrderType::class;

    public function findByService(int $serviceId): ?WorkOrderType
    {
        $rows = $this->findAll(['service_id' => $serviceId], 'id');

        return $rows[0] ?? null;
    }

    /** @return WorkOrderType[] i processi in uso non ancora collegati a un servizio */
    public function findWithoutService(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM work_order_types WHERE status != 0 AND service_id IS NULL AND (stage IS NULL OR stage != 'non in uso') ORDER BY name");

        return array_map(static fn (array $row) => WorkOrderType::fromArray($row), $rows);
    }
}
