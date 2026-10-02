<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AgentMessage;

final class AgentMessageRepository extends AbstractRepository
{
    protected string $table = 'agent_messages';
    protected string $modelClass = AgentMessage::class;

    /** @return AgentMessage[] i piu' recenti per primi */
    public function findRecent(int $limit): array
    {
        return $this->paginate(1, $limit, [], 'created_at DESC, id DESC')['items'];
    }
}
