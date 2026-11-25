<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AssistantAction;

final class AssistantActionRepository extends AbstractRepository
{
    protected string $table = 'assistant_actions';
    protected string $modelClass = AssistantAction::class;

    public function findByKey(string $key): ?AssistantAction
    {
        $rows = $this->findAll(['dedupe_key' => $key], 'id DESC');

        return $rows[0] ?? null;
    }

    /** @return AssistantAction[] le proposte che aspettano la decisione della persona */
    public function openProposalsFor(int $userId): array
    {
        return $this->findAll(['user_id' => $userId, 'stage' => 'proposta'], 'created_at DESC, id DESC');
    }

    /** @return AssistantAction[] */
    public function recentFor(int $userId, int $days): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM assistant_actions WHERE status != 0 AND user_id = :u AND stage IN ('eseguita', 'scartata')
               AND decided_at >= :since ORDER BY decided_at DESC LIMIT 10",
            ['u' => $userId, 'since' => date('Y-m-d H:i:s', strtotime("-{$days} days"))]
        );

        return array_map(static fn (array $row) => AssistantAction::fromArray($row), $rows);
    }

    /** @return AssistantAction[] tutto quello che l'assistente ha fatto o preparato per la persona negli ultimi giorni */
    public function activityFor(int $userId, int $days): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM assistant_actions WHERE status != 0 AND user_id = :u AND created_at >= :since ORDER BY COALESCE(decided_at, created_at) DESC, id DESC LIMIT 40",
            ['u' => $userId, 'since' => date('Y-m-d H:i:s', strtotime("-{$days} days"))]
        );

        return array_map(static fn (array $row) => AssistantAction::fromArray($row), $rows);
    }
}
