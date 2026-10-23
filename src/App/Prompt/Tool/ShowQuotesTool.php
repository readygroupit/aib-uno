<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Quotes\QuoteOverviewService;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\SimpleBoardComponent;
use App\View\Component\StatBoxComponent;

/** I preventivi per cosa c'e' da fare: da approvare, in preparazione, dal cliente, chiusi. */
final class ShowQuotesTool implements PromptToolInterface, PromptConversationalInterface
{
    private Container $container;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'show_quotes';
    }

    public function description(): string
    {
        return "Preventivi: da approvare, in preparazione, in attesa del cliente, accettati e rifiutati, con il valore aperto e quanti se ne chiudono. "
            . "Usalo per 'preventivi', 'offerte', 'proposte ai clienti', 'quanti preventivi abbiamo chiuso'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Preventivi';
    }

    public function menuSection(): ?string
    {
        return 'vendite';
    }

    public function menuCount(): ?string
    {
        $t = $this->container->get(QuoteOverviewService::class)->board($this->auth->hasPermission('quotes.approve'))['totals'];

        return $this->auth->hasPermission('quotes.approve') && $t['toApprove'] > 0 ? $t['toApprove'] . ' da approvare' : ($t['openCount'] > 0 ? (string) $t['openCount'] : null);
    }

    public function requiredPermission(): ?string
    {
        return 'quotes.view';
    }

    public function triggers(): array
    {
        return ['preventivi', 'offerte', 'preventivo', 'proposte'];
    }

    public function execute(array $input): array
    {
        $board = $this->container->get(QuoteOverviewService::class)->board($this->auth->hasPermission('quotes.approve'));
        $t = $board['totals'];
        $statBox = $this->container->get(StatBoxComponent::class);

        return [$this->container->get(SimpleBoardComponent::class)->toData([
            'title' => 'Preventivi',
            'url' => '/preventivi',
            'createHref' => $this->auth->hasPermission('quotes.create') ? '/preventivi/nuovo' : null,
            'createLabel' => 'Nuovo preventivo',
            'stats' => [
                $statBox->toData([
                    'label' => 'Aperti',
                    'value' => F::euro($t['openValue']),
                    'comparison' => F::plural($t['openCount'], 'preventivo', 'preventivi') . ($t['sent'] > 0 ? ", {$t['sent']} dal cliente" : ''),
                    'feature' => true,
                    'badge' => 'In corso',
                ]),
                $statBox->toData([
                    'label' => 'Da approvare',
                    'value' => (string) $t['toApprove'],
                    'comparison' => 'prima di mandarli',
                    'dotColor' => $t['toApprove'] > 0 ? 'brass' : 'moss',
                ]),
                $statBox->toData([
                    'label' => 'Chiusi in 90 giorni',
                    'value' => $t['winRate'] !== null ? $t['winRate'] . '%' : '—',
                    'comparison' => 'accettati, per ' . F::euro($t['wonValue']),
                    'dotColor' => 'moss',
                ]),
            ],
            'sections' => $board['sections'],
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $t = $this->container->get(QuoteOverviewService::class)->board($this->auth->hasPermission('quotes.approve'))['totals'];

        return F::plural($t['openCount'], 'preventivo aperto', 'preventivi aperti') . ' per ' . F::euro($t['openValue'])
            . ($t['toApprove'] > 0 ? ", {$t['toApprove']} da approvare" : '') . '.';
    }

    public function followUpSuggestions(): array
    {
        return ['Nuovo preventivo', 'Clienti', 'Servizi'];
    }
}
