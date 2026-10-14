<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Operato dei colleghi digitali agente per agente: questa settimana contro
 * la precedente, con completate e in attesa (vedi agent-activity.js e
 * ShowAgentActivityTool).
 */
final class AgentActivityComponent extends AbstractComponent
{
    /**
     * @param array{agents: list<array{code: string, name: string, role: string, thisWeek: int, lastWeek: int, done: int, pending: int, dismissed: int}>} $config
     */
    public function toData(array $config): array
    {
        $agents = $config['agents'] ?? [];
        usort($agents, static fn (array $a, array $b) => [$b['thisWeek'], $b['done']] <=> [$a['thisWeek'], $a['done']]);
        $max = max(1, ...array_map(static fn (array $a) => max($a['thisWeek'], $a['lastWeek']), $agents ?: [['thisWeek' => 0, 'lastWeek' => 0]]));

        return [
            'type' => 'agent-activity',
            'title' => $config['title'] ?? 'Agente per agente',
            'subtitle' => $config['subtitle'] ?? null,
            'max' => $max,
            'agents' => $agents,
        ];
    }
}
