<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AiAgentTask;

final class AiAgentTaskRepository extends AbstractRepository
{
    protected string $table = 'ai_agent_tasks';
    protected string $modelClass = AiAgentTask::class;
}
