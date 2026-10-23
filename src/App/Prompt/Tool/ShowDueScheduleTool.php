<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Billing\AccountOverviewService;
use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Support\ItalianFormat as F;
use App\View\Component\DueBoardComponent;
use App\View\Component\StatBoxComponent;

/**
 * "Chi deve pagare e cosa scade": lo scadenzario degli incassi e dei
 * rinnovi, per urgenza.
 */
final class ShowDueScheduleTool implements PromptToolInterface, PromptConversationalInterface
{
    private AccountOverviewService $overview;
    private StatBoxComponent $statBox;
    private DueBoardComponent $board;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(AccountOverviewService::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->board = $container->get(DueBoardComponent::class);
    }

    public function name(): string
    {
        return 'show_due_schedule';
    }

    public function description(): string
    {
        return "Scadenzario: pagamenti scaduti, incassi attesi nei prossimi giorni, rinnovi dei servizi in arrivo "
            . "e ultimi incassi. Usalo per 'scadenzario', 'chi deve pagare', 'incassi', 'rinnovi', 'scadenze'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Scadenzario';
    }

    public function menuSection(): ?string
    {
        return 'vendite';
    }

    public function menuCount(): ?string
    {
        $totals = $this->overview->dueBoard()['totals'];

        return $totals['overdueCount'] > 0 ? $totals['overdueCount'] . ' scaduti' : null;
    }

    public function requiredPermission(): ?string
    {
        return 'installments.view';
    }

    public function triggers(): array
    {
        return ['scadenzario', 'scadenze', 'incassi', 'rinnovi', 'chi deve pagare', 'pagamenti scaduti'];
    }

    public function execute(array $input): array
    {
        $board = $this->overview->dueBoard();
        $t = $board['totals'];

        $stats = [
            $this->statBox->toData([
                'label' => 'Da incassare',
                'value' => F::euro($t['next30']),
                'comparison' => 'nei prossimi 30 giorni, ' . F::plural($t['next30Count'], 'pagamento', 'pagamenti'),
                'feature' => true,
                'badge' => 'Prossimi 30 giorni',
            ]),
            $this->statBox->toData([
                'label' => 'Scaduti',
                'value' => F::euro($t['overdue']),
                'comparison' => $t['overdueCount'] > 0 ? F::plural($t['overdueCount'], 'pagamento da sollecitare', 'pagamenti da sollecitare') : 'nessun ritardo',
                'dotColor' => $t['overdue'] > 0 ? 'danger' : 'moss',
            ]),
            $this->statBox->toData([
                'label' => 'Incassato a ' . F::monthName((int) date('n')),
                'value' => F::euro($t['paidThisMonth']),
                'comparison' => 'ultimi 6 mesi',
                'dotColor' => 'moss',
                'sparkline' => array_values($board['paidPerMonth']),
                'sparklineStyle' => 'bars',
            ]),
            $this->statBox->toData([
                'label' => 'Rinnovi vicini',
                'value' => (string) $t['renewalsCount'],
                'comparison' => $t['renewalsCount'] > 0 ? F::euro($t['renewalsValue']) . ' entro ' . AccountOverviewService::RENEWAL_HORIZON_DAYS . ' giorni' : 'nessuno entro ' . AccountOverviewService::RENEWAL_HORIZON_DAYS . ' giorni',
                'dotColor' => 'brass',
            ]),
        ];

        return [$this->board->toData([
            'stats' => $stats,
            'groups' => $board['groups'],
            'renewals' => $board['renewals'],
            'recentPaid' => $board['recentPaid'],
            'createHref' => '/scadenzario/nuovo',
            'createLabel' => 'Nuovo incasso',
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $t = $this->overview->dueBoard()['totals'];
        $parts = [];
        if ($t['overdue'] > 0) {
            $parts[] = F::euro($t['overdue']) . ' sono scaduti (' . F::plural($t['overdueCount'], 'pagamento', 'pagamenti') . ' da sollecitare)';
        }
        $parts[] = F::euro($t['next30']) . ' arrivano nei prossimi 30 giorni';
        if ($t['renewalsCount'] > 0) {
            $parts[] = F::plural($t['renewalsCount'], 'servizio si rinnova', 'servizi si rinnovano') . ' entro ' . AccountOverviewService::RENEWAL_HORIZON_DAYS . ' giorni';
        }

        return ucfirst(implode(', ', $parts)) . '. Questo mese hai incassato ' . F::euro($t['paidThisMonth']) . '.';
    }

    public function followUpSuggestions(): array
    {
        return ['Clienti con pagamenti scaduti', 'Rinnovi vicini', 'Clienti'];
    }
}
