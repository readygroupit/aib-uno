<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Planner\PlannerOverviewService;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\View\Component\TeamPlanComponent;

/** Il team: chi e' pieno, chi e' libero, cosa sta facendo adesso, la settimana. */
final class ShowTeamPlanTool implements PromptToolInterface, PromptConversationalInterface
{
    private PlannerOverviewService $overview;
    private TeamPlanComponent $component;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(PlannerOverviewService::class);
        $this->component = $container->get(TeamPlanComponent::class);
    }

    public function name(): string
    {
        return 'show_team';
    }

    public function description(): string
    {
        return "Il lavoro del team: per ognuno il carico di oggi, cosa sta facendo, chi e' libero, le proposte per "
            . "riequilibrare, e la settimana giorno per giorno. Usalo per 'il team', 'chi e' libero', 'cosa stanno facendo', "
            . "'carico di lavoro', 'chi e' sovraccarico'. Solo per tutto il team: se la richiesta nomina una persona "
            . "(anche per una settimana) usa show_my_day con person.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['week' => ['type' => 'string', 'description' => 'Una data della settimana da mostrare (AAAA-MM-GG)']]];
    }

    public function menuLabel(): ?string
    {
        return 'Il team';
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
        return 'planner.team';
    }

    public function triggers(): array
    {
        return ['team', 'il team', 'chi e libero', "chi e' libero", 'chi è libero', 'carico del team', 'cosa stanno facendo', 'squadra'];
    }

    public function execute(array $input): array
    {
        $date = isset($input['week']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['week']) === 1 ? (string) $input['week'] : date('Y-m-d');
        $monday = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $team = $this->overview->team(date('Y-m-d'));

        return [$this->component->toData([
            'url' => '/team' . ($monday !== date('Y-m-d', strtotime('monday this week')) ? '?settimana=' . $monday : ''),
            'people' => $team['people'],
            'suggestions' => $team['suggestions'],
            'week' => $this->overview->week($monday),
            'prevWeek' => '/team?settimana=' . date('Y-m-d', strtotime("{$monday} -7 days")),
            'nextWeek' => '/team?settimana=' . date('Y-m-d', strtotime("{$monday} +7 days")),
            'schedulesHref' => '/orari',
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $team = $this->overview->team(date('Y-m-d'));
        $parts = [];
        foreach ($team['people'] as $p) {
            if (!$p['working']) {
                continue;
            }
            $parts[] = "{$p['name']} {$p['load']}%" . ($p['overflowLabel'] !== null ? " ({$p['overflowLabel']})" : ($p['freeLabel'] !== null ? " ({$p['freeLabel']})" : ''));
        }
        $sentence = 'Oggi: ' . implode(', ', $parts) . '.';
        if ($team['suggestions'] !== []) {
            $sentence .= ' ' . $team['suggestions'][0]['text'];
        }

        return $sentence;
    }

    public function followUpSuggestions(): array
    {
        return ['La mia giornata', 'Commesse', 'Calendario'];
    }
}
