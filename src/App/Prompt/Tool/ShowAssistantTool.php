<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Assistant\AssistantService;
use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Service\AuthService;

/**
 * La giornata secondo l'assistente: cosa decidere, fare, fissare, risentire.
 * E' anche la home di chi non ha un cruscotto (IndexController): entrando
 * l'assistente parla per primo.
 */
final class ShowAssistantTool implements PromptToolInterface, PromptConversationalInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function name(): string
    {
        return 'show_assistant';
    }

    public function description(): string
    {
        return "Cosa deve fare e decidere oggi chi scrive, secondo l'assistente: approvazioni, date chiave da fissare, ruoli scoperti, date in arrivo, clienti da risentire (con i messaggi pronti), idee. Per \"cosa devo fare\", \"cosa c'e' da decidere\", \"aiutami\", \"le mie cose\".";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'I tuoi agenti';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function triggers(): array
    {
        return ['assistente', 'agenti', 'i miei agenti', "cosa c'e' da decidere", 'cosa devo decidere', 'le mie cose'];
    }

    public function execute(array $input): array
    {
        $service = $this->container->get(AssistantService::class);
        $auth = $this->container->get(AuthService::class);
        // La home: la ricerca resta al centro, sotto i colleghi digitali (homeBelow, vedi hero.js).
        if (!empty($input['home'])) {
            $home = $service->colleaguesFor((int) $auth->currentUserId());

            return [[
                'type' => 'assistant-colleagues',
                'homeBelow' => true,
                'summary' => $home['summary'],
                'colleagues' => $home['colleagues'],
                'agendaHref' => '/assistente',
            ]];
        }
        $hour = (int) date('G');
        $name = $service->currentUserFirstName();

        // La pagina degli agenti (/assistente): uno per volta, con le sue cose,
        // cosa ha fatto e come lavora. La vecchia giornata a sezioni resta per ?vista=giornata.
        if (($input['view'] ?? 'agents') !== 'agenda') {
            $agents = $service->agentsFor((int) $auth->currentUserId(), $name, $auth->hasPermission('assistant.manage'));
            $pick = (string) ($input['agent'] ?? '');
            if (!in_array($pick, array_column($agents, 'key'), true)) {
                $busiest = $agents;
                usort($busiest, static fn (array $a, array $b) => [['urgent' => 0, 'soon' => 1, 'calm' => 2, 'none' => 3][$a['tone']], -$a['count']] <=> [['urgent' => 0, 'soon' => 1, 'calm' => 2, 'none' => 3][$b['tone']], -$b['count']]);
                $pick = $busiest[0]['key'] ?? 'projects';
            }

            return [[
                'type' => 'assistant-agents',
                'title' => 'I tuoi agenti',
                'intro' => 'Lavorano per te' . ($name !== '' ? ", {$name}" : '') . ": seguono i tuoi progetti e i tuoi clienti, ti dicono cosa fare e fanno da soli quello che e' stato deciso di lasciargli fare.",
                'agents' => $agents,
                'selected' => $pick,
                'rulesHref' => $auth->hasPermission('assistant.manage') ? '/assistente/regole' : null,
            ]];
        }
        $agenda = $service->agendaFor((int) $auth->currentUserId());

        return [[
            'type' => 'assistant-agenda',
            'greeting' => ($hour < 13 ? 'Buongiorno' : ($hour < 18 ? 'Buon pomeriggio' : 'Buonasera')) . ($name !== '' ? ", {$name}" : ''),
            'summary' => $this->summary($agenda),
            'sections' => $agenda['sections'],
            'done' => $agenda['done'],
            'rulesHref' => $auth->hasPermission('assistant.manage') ? '/assistente/regole' : null,
        ]];
    }

    public function replySummary(array $input): string
    {
        return '';
    }

    public function followUpSuggestions(): array
    {
        return ['oggi', 'commesse'];
    }

    private function summary(array $agenda): string
    {
        if ($agenda['count'] === 0) {
            return "Per ora e' tutto in ordine: niente da decidere ne' da risentire.";
        }
        $parts = [];
        foreach ($agenda['sections'] as $section) {
            $n = count($section['items']);
            $parts[] = match ($section['key']) {
                'decide' => $n === 1 ? '1 cosa da decidere' : "{$n} cose da decidere",
                'today' => $n === 1 ? '1 da fare' : "{$n} da fare",
                'schedule' => $n === 1 ? '1 da fissare' : "{$n} da fissare",
                'soon' => $n === 1 ? '1 in arrivo' : "{$n} in arrivo",
                'followup' => $n === 1 ? '1 cliente da risentire' : "{$n} da risentire",
                'ideas' => $n === 1 ? "un'idea" : "{$n} idee",
                default => (string) $n,
            };
        }

        return 'Oggi: ' . implode(', ', $parts) . '.';
    }
}
