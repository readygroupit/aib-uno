<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkEntry;

final class WorkEntryRepository extends AbstractRepository
{
    protected string $table = 'work_entries';
    protected string $modelClass = WorkEntry::class;

    /** @return WorkEntry[] di una persona tra due giorni (inclusi) */
    public function findForUserBetween(int $userId, string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM work_entries WHERE status != 0 AND user_id = :u AND work_date BETWEEN :from AND :to ORDER BY work_date, id',
            ['u' => $userId, 'from' => $from, 'to' => $to]
        );

        return array_map(static fn (array $row) => WorkEntry::fromArray($row), $rows);
    }

    /** Minuti registrati su un'attivita', da tutti. */
    public function minutesForTask(int $taskId): int
    {
        $row = $this->db->fetchRow('SELECT COALESCE(SUM(minutes), 0) AS m FROM work_entries WHERE status != 0 AND task_id = :t', ['t' => $taskId]);

        return (int) ($row['m'] ?? 0);
    }

    /** @return array<int, int> persona => minuti registrati nel periodo */
    public function minutesPerUserBetween(string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            'SELECT user_id, SUM(minutes) AS m FROM work_entries WHERE status != 0 AND work_date BETWEEN :from AND :to GROUP BY user_id',
            ['from' => $from, 'to' => $to]
        );

        return array_map('intval', array_column($rows, 'm', 'user_id'));
    }

    /** @return int[] id delle attivita' gia' registrate da una persona in un giorno */
    public function taskIdsLogged(int $userId, string $date): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT task_id FROM work_entries WHERE status != 0 AND user_id = :u AND work_date = :d AND task_id IS NOT NULL',
            ['u' => $userId, 'd' => $date]
        );

        return array_map('intval', array_column($rows, 'task_id'));
    }
}
