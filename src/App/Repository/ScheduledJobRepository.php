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

    /**
     * Un job ricorrente "di sistema" (es. la sincronizzazione del
     * calendario): lo crea se non c'e', cosi' basta il cron di
     * bin/run-scheduler.php e nessuno deve programmarlo a mano.
     */
    public function ensureRecurring(string $jobType, int $intervalMinutes): void
    {
        $row = $this->db->fetchRow(
            'SELECT id FROM scheduled_jobs WHERE status != 0 AND job_type = :type AND interval_minutes IS NOT NULL LIMIT 1',
            ['type' => $jobType]
        );
        if ($row !== null) {
            return;
        }

        $this->insert([
            'job_type' => $jobType,
            'payload' => '{}',
            'run_at' => date('Y-m-d H:i:s'),
            'interval_minutes' => $intervalMinutes,
        ]);
    }
}
