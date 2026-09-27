<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ScheduledJob;

final class ScheduledJobRepository extends AbstractRepository
{
    protected string $table = 'scheduled_jobs';
    protected string $modelClass = ScheduledJob::class;

    /**
     * Job attivi la cui prossima esecuzione e' arrivata o passata.
     *
     * @return ScheduledJob[]
     */
    public function findDue(): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE status = 1 AND run_at <= NOW() ORDER BY run_at ASC";

        return array_map(
            static fn (array $row) => ScheduledJob::fromArray($row),
            $this->db->fetchAll($sql)
        );
    }
}
