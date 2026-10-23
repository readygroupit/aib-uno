<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\LeaveRequest;

final class LeaveRequestRepository extends AbstractRepository
{
    protected string $table = 'leave_requests';
    protected string $modelClass = LeaveRequest::class;

    /** @return LeaveRequest[] approvate che toccano il periodo (tutte le persone se $userId e' null) */
    public function findApprovedBetween(string $from, string $to, ?int $userId = null): array
    {
        $sql = "SELECT * FROM leave_requests WHERE status != 0 AND stage = 'approvata'
                AND start_date <= :to AND COALESCE(end_date, start_date) >= :from";
        $params = ['from' => $from, 'to' => $to];
        if ($userId !== null) {
            $sql .= ' AND user_id = :user';
            $params['user'] = $userId;
        }
        $rows = $this->db->fetchAll($sql . ' ORDER BY start_date, id', $params);

        return array_map(static fn (array $row) => LeaveRequest::fromArray($row), $rows);
    }

    /** @return LeaveRequest[] */
    public function findPending(): array
    {
        return $this->findAll(['stage' => 'richiesta'], 'start_date, id');
    }

    /** @return LeaveRequest[] di una persona, dalle prossime */
    public function findForUser(int $userId): array
    {
        return $this->findAll(['user_id' => $userId], 'start_date DESC, id DESC');
    }
}
