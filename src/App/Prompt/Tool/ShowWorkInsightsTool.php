<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Support\ItalianFormat as F;
use App\View\Component\WorkInsightsComponent;
use App\Work\WorkOrderOverviewService;
use App\Worklog\WorklogOverviewService;

/** Pannello dei titolari: quanto durano davvero i lavori e cosa va guardato. */
final class ShowWorkInsightsTool implements PromptToolInterface, PromptConversationalInterface
{
    private WorklogOverviewService $overview;
    private WorkInsightsComponent $component;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(WorklogOverviewService::class);
        $this->component = $container->get(WorkInsightsComponent::class);
    }

    public function name(): string
    {
        return 'show_work_insights';
    }

    public function description(): string
    {
        return "Per i titolari: tempi medi reali per tipo di lavoro rispetto al previsto, lavori durati molto piu' del solito, "
            . "chi impiega di piu' sullo stesso lavoro, attivita' e commesse ferme, chi non ha segnato, chi e' oltre il carico. "
            . "Usalo per 'tempi', 'quanto ci mettiamo', 'andamento del lavoro', 'chi non ha segnato'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Tempi e andamento';
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
        return 'worklog.team';
    }

    public function triggers(): array
    {
        return ['tempi', 'tempi medi', 'andamento', 'andamento del lavoro', 'quanto ci mettiamo', 'chi non ha segnato'];
    }

    public function execute(array $input): array
    {
        $data = $this->overview->insights();

        return [$this->component->toData($data + [
            'url' => '/registro/andamento',
            'weekTotal' => WorkOrderOverviewService::duration($data['weekMinutes']),
            'privacy' => "Questi dati servono a capire quanto durano i lavori e dove serve una mano, non a controllare le persone. "
                . "Prima di usarli con i dipendenti: informativa privacy e verifica con il consulente del lavoro (art. 4 dello Statuto dei Lavoratori).",
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $data = $this->overview->insights();
        $parts = [F::plural(count($data['report']), 'tipo di lavoro misurato', 'tipi di lavoro misurati')];
        $over = array_filter($data['report'], static fn (array $r) => ($r['delta'] ?? 0) >= 25);
        if ($over !== []) {
            $first = reset($over);
            $parts[] = "il piu' sottostimato e' \u{ab}{$first['label']}\u{bb} ({$first['real']} contro {$first['planned']} previste)";
        }
        if ($data['anomalies'] !== []) {
            $parts[] = F::plural(count($data['anomalies']), 'caso da guardare', 'casi da guardare');
        }
        if ($data['missing'] !== []) {
            $parts[] = 'oggi non hanno ancora segnato: ' . implode(', ', array_column($data['missing'], 'name'));
        }

        return ucfirst(implode('; ', $parts)) . '.';
    }

    public function followUpSuggestions(): array
    {
        return ['Il team', 'Commesse', 'Il mio registro'];
    }
}
