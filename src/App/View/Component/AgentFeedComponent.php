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

        return [
            'type' => 'agent-feed',
            'items' => $service->feed((int) ($config['limit'] ?? 6)),
            'canEdit' => $this->container->get(AuthService::class)->hasPermission('agents.edit'),
        ];
    }
}
