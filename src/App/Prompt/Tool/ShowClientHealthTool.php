<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\ClientHealth\HealthOverviewService;
use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Support\ItalianFormat as F;
use App\View\Component\ClientHealthComponent;
use App\View\Component\StatBoxComponent;

/** Quali clienti si stanno raffreddando, perche' e cosa fare. */
final class ShowClientHealthTool implements PromptToolInterface, PromptConversationalInterface
{
    private HealthOverviewService $overview;
    private ClientHealthComponent $component;
    private StatBoxComponent $statBox;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(HealthOverviewService::class);
        $this->component = $container->get(ClientHealthComponent::class);
        $this->statBox = $container->get(StatBoxComponent::class);
    }

    public function name(): string
    {
        return 'show_client_health';
    }

    public function description(): string
    {
        return "Temperatura dei clienti: chi rischia di non rinnovare o si sta raffreddando (non lo si sente da tempo, "
            . "ultimo contatto andato male, risultati scarsi, pagamenti in ritardo), con il motivo e cosa fare. "
            . "Usalo per 'clienti a rischio', 'chi devo chiamare', 'clienti freddi', 'temperatura clienti'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Clienti a rischio';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function menuCount(): ?string
    {
        $risk = $this->overview->board()['counts']['rischio'];

        return $risk > 0 ? (string) $risk : null;
    }

    public function requiredPermission(): ?string
    {
        return 'client_health.view';
    }

    public function triggers(): array
    {
        return ['clienti a rischio', 'temperatura clienti', 'chi devo chiamare', 'clienti freddi'];
    }

    public function execute(array $input): array
    {
        $board = $this->overview->board();
        $c = $board['counts'];

        return [$this->component->toData([
            'stats' => [
                $this->statBox->toData([
                    'label' => 'A rischio',
                    'value' => (string) $c['rischio'],
                    'comparison' => $c['rischio'] > 0 ? 'da sentire questa settimana' : 'nessun cliente a rischio',
                    'feature' => true,
                    'badge' => 'Adesso',
                ]),
                $this->statBox->toData(['label' => 'Da seguire', 'value' => (string) $c['seguire'], 'comparison' => 'qualche segnale da non lasciare correre', 'dotColor' => 'brass']),
                $this->statBox->toData(['label' => 'Stanno bene', 'value' => (string) $c['bene'], 'comparison' => 'clienti con servizi attivi', 'dotColor' => 'moss']),
            ],
            'rows' => $board['rows'],
            'recent' => $board['recent'],
            'createHref' => '/clienti/contatti/nuovo',
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $board = $this->overview->board();
        $risk = array_values(array_filter($board['rows'], static fn (array $r) => $r['level'] === 'rischio'));
        if ($risk === []) {
            return 'Nessun cliente a rischio. ' . F::plural($board['counts']['seguire'], 'cliente e\' da seguire', 'clienti sono da seguire') . '.';
        }

        return F::plural(count($risk), 'cliente e\' a rischio', 'clienti sono a rischio') . ': '
            . implode(', ', array_map(static fn (array $r) => $r['name'] . ' (' . ($r['reasons'][0] ?? '') . ')', array_slice($risk, 0, 3)))
            . (count($risk) > 3 ? ' e altri.' : '.');
    }

    public function followUpSuggestions(): array
    {
        return ['Clienti', 'Scadenzario', 'Le mie attivita'];
    }
}
