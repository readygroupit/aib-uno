<?php

declare(strict_types=1);

namespace App\View\Component;

use App\Service\AgentService;
use App\Service\AuthService;

/** La pagina Agenti: una scheda per agente, da programmare (vedi agents-board.js). */
final class AgentsBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        $agents = array_map(static fn ($agent) => [
            'id' => $agent->id,
            'code' => $agent->code,
            'name' => $agent->name,
            'role' => $agent->role,
            'bio' => $agent->bio,
            'enabled' => (bool) $agent->isEnabled,
            'schedule' => $agent->schedule,
            'autonomy' => $agent->autonomy,
            'rule' => $agent->ruleText,
            'lastRunAt' => $agent->lastRunAt,
        ], $this->container->get(AgentService::class)->agents());

        return [
            'type' => 'agents-board',
            'agents' => $agents,
            'schedules' => AgentService::SCHEDULES,
            'canEdit' => $this->container->get(AuthService::class)->hasPermission('agents.edit'),
        ];
    }
}
