<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkCheckin;

final class WorkCheckinRepository extends AbstractRepository
{
    protected string $table = 'work_checkins';
    protected string $modelClass = WorkCheckin::class;

    public function lastForUser(int $userId): ?WorkCheckin
    {
        $row = $this->db->fetchRow('SELECT * FROM work_checkins WHERE status != 0 AND user_id = :u ORDER BY checked_at DESC LIMIT 1', ['u' => $userId]);

        return $row !== null ? WorkCheckin::fromArray($row) : null;
    }

    /** @return int[] persone che hanno risposto almeno una volta nel giorno */
    public function usersCheckedOn(string $date): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT user_id FROM work_checkins WHERE status != 0 AND DATE(checked_at) = :d',
            ['d' => $date]
        );

        return array_map('intval', array_column($rows, 'user_id'));
    }
}
