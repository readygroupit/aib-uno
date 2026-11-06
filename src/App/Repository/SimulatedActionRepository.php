<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\SimulatedAction;

final class SimulatedActionRepository extends AbstractRepository
{
    protected string $table = 'simulated_actions';
    protected string $modelClass = SimulatedAction::class;

    /** @return SimulatedAction[] le piu' recenti */
    public function findRecent(int $limit): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM simulated_actions WHERE status != 0 ORDER BY id DESC LIMIT ' . max(1, $limit));

        return array_map(static fn (array $row) => SimulatedAction::fromArray($row), $rows);
    }
}
