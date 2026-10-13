<?php

declare(strict_types=1);

namespace App\View\Component;

use App\Service\AgentService;
use App\Service\AuthService;

/** Messaggi degli agenti per l'operatore (vedi public/js/components/agent-feed.js). */
final class AgentFeedComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        $service = $this->container->get(AgentService::class);

        // Colonna "Agenti al lavoro": chi c'e', quanta autonomia ha e quante
        // cose aspettano il tuo OK (contate sugli ultimi messaggi).
        $pending = [];
        foreach ($service->feed(40) as $item) {
            if ($item['state'] === 'pending' && $item['actionType'] !== null) {
                $pending[$item['agentCode']] = ($pending[$item['agentCode']] ?? 0) + 1;
            }
        }
        $agents = array_map(static fn ($agent) => [
            'code' => $agent->code,
            'name' => $agent->name,
            'role' => $agent->role,
            'autonomy' => $agent->autonomy,
            'enabled' => (bool) $agent->isEnabled,
            'pending' => $pending[$agent->code] ?? 0,
        ], $service->agents());

        return [
            'type' => 'agent-feed',
            'items' => $service->feed((int) ($config['limit'] ?? 6)),
            'agents' => $agents,
            'canEdit' => $this->container->get(AuthService::class)->hasPermission('agents.edit'),
        ];
    }
}
