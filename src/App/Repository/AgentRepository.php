<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Agent;

final class AgentRepository extends AbstractRepository
{
    protected string $table = 'agents';
    protected string $modelClass = Agent::class;
}
