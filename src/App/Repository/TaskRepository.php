<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Task;

final class TaskRepository extends AbstractRepository
{
    protected string $table = 'tasks';
    protected string $modelClass = Task::class;

    /** Attivita' attive non completate con scadenza entro $before (formato 'Y-m-d H:i:s') - usato dal Report. */
    public function countDueBy(string $before): int
    {
        $row = $this->db->fetchRow(
            'SELECT COUNT(*) AS total FROM tasks WHERE status != 0 AND completed_at IS NULL AND due_at IS NOT NULL AND due_at <= :before',
            ['before' => $before]
        );

        return (int) ($row['total'] ?? 0);
    }

    /** @return Task[] le fasi/attivita' di un elemento (es. una commessa), in ordine */
    public function findForEntity(string $entityType, int $entityId): array
    {
        return $this->findAll(['entity_type' => $entityType, 'entity_id' => $entityId], 'position IS NULL, position, planned_date, id');
    }

    /**
     * Le attivita' di tutte le commesse indicate, in una query.
     *
     * @return array<int, Task[]> id commessa => attivita' in ordine
     */
    public function findForEntities(string $entityType, array $entityIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $entityIds))));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->db->fetchAll(
            "SELECT * FROM tasks WHERE status != 0 AND entity_type = ? AND entity_id IN ({$placeholders})
             ORDER BY entity_id, position IS NULL, position, planned_date, id",
            [$entityType, ...$ids]
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['entity_id']][] = Task::fromArray($row);
        }

        return $grouped;
    }

    /**
     * Attivita' aperte (di una persona, o di tutti con null) piu' quelle
     * fatte da $doneSince in poi.
     *
     * @return Task[]
     */
    public function findForBoard(?int $userId, string $doneSince): array
    {
        $params = ['since' => $doneSince];
        $userSql = '';
        if ($userId !== null) {
            $userSql = ' AND assigned_user_id = :user';
            $params['user'] = $userId;
        }

        $rows = $this->db->fetchAll(
            "SELECT * FROM tasks
             WHERE status != 0{$userSql}
               AND (stage IN ('da fare', 'in corso') OR stage IS NULL OR (stage = 'fatta' AND completed_at >= :since))
             ORDER BY COALESCE(planned_date, DATE(due_at)) IS NULL, COALESCE(planned_date, DATE(due_at)),
                      FIELD(priority, 'alta', 'normale', 'bassa'), position, id",
            $params
        );

        return array_map(static fn (array $row) => Task::fromArray($row), $rows);
    }

    /**
     * Attivita' aperte previste per giorno, nel periodo: quante e quanto
     * tempo. Per la testata dei giorni del calendario.
     *
     * @return array<string, array{count:int, minutes:int}>
     */
    public function openPerDay(string $from, string $to, ?int $userId = null): array
    {
        $params = ['from' => $from, 'to' => $to];
        $userSql = '';
        if ($userId !== null) {
            $userSql = ' AND assigned_user_id = :user';
            $params['user'] = $userId;
        }
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(planned_date, DATE(due_at)) AS day, COUNT(*) AS n, COALESCE(SUM(estimated_minutes), 0) AS minutes
             FROM tasks
             WHERE status != 0 AND (stage IN ('da fare', 'in corso') OR stage IS NULL)
               AND COALESCE(planned_date, DATE(due_at)) BETWEEN :from AND :to{$userSql}
             GROUP BY day",
            $params
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['day']] = ['count' => (int) $row['n'], 'minutes' => (int) $row['minutes']];
        }

        return $result;
    }

    /**
     * Attivita' fatte con il tempo impiegato, dal giorno indicato: la base
     * dei tempi medi (App\Worklog\DurationStats).
     *
     * @return Task[]
     */
    public function findDoneWithTimeSince(string $since): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM tasks WHERE status != 0 AND stage = 'fatta' AND actual_minutes > 0 AND completed_at >= :since ORDER BY completed_at DESC",
            ['since' => $since]
        );

        return array_map(static fn (array $row) => Task::fromArray($row), $rows);
    }
}
