<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * "AI Orchestrator - agenti al lavoro": traduzione diretta della tabella
 * degli agenti del brief Assilevi (6 Agenti AI di Assilevi) in una card
 * di stato. Onesto su cosa e' reale: 'summary' quando possibile viene da
 * un conteggio vero (vedi ShowHomeDashboardTool), il 'level' invece
 * dichiara il livello di automazione TARGET per quell'agente (10 Livelli
 * di automazione) - non "sta succedendo ora in autonomia", perche' nessun
 * agente qui gira davvero in autonomia ancora: solo lead intake e
 * qualificazione hanno una logica reale dietro (dedup/campi strutturati),
 * gli altri sono ancora manuali - vedi 'level' => 'manuale' per quelli.
 */
final class AgentPanelComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'agent-panel',
            'title' => $config['title'] ?? '',
            'subtitle' => $config['subtitle'] ?? '',
            'note' => $config['note'] ?? null,
            // [{name, summary, level}] level: 'autonomo'|'assistito'|'manuale'
            'agents' => $config['agents'] ?? [],
        ];
    }
}
