<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Invoicing\InvoiceOverviewService;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Support\ItalianFormat as F;
use App\View\Component\InvoiceBoardComponent;
use App\View\Component\StatBoxComponent;

/** Le fatture per cosa c'e' da fare: da fatturare, bozze, SdI, da incassare. */
final class ListInvoicesTool implements PromptToolInterface, PromptConversationalInterface
{
    private InvoiceOverviewService $overview;
    private StatBoxComponent $statBox;
    private InvoiceBoardComponent $board;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(InvoiceOverviewService::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->board = $container->get(InvoiceBoardComponent::class);
    }

    public function name(): string
    {
        return 'list_invoices';
    }

    public function description(): string
    {
        return "Fatture: incassi ancora da fatturare, bozze da emettere, fatture da inviare allo SdI, da incassare e pagate. "
            . "Usalo per 'fatture', 'cosa devo fatturare', 'fatture da inviare', 'fatture non pagate'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Fatture';
    }

    public function menuSection(): ?string
    {
        return 'vendite';
    }

    public function menuCount(): ?string
    {
        $t = $this->overview->board()['totals'];
        $todo = $t['toInvoiceCount'] + $t['draftsCount'] + $t['toSendCount'];

        return $todo > 0 ? $todo . ' da fare' : null;
    }

    public function requiredPermission(): ?string
    {
        return 'invoices.view';
    }

    public function triggers(): array
    {
        return ['fatture', 'fatturare', 'da fatturare', 'fatture da inviare', 'fatture non pagate', 'sdi'];
    }

    public function execute(array $input): array
    {
        $board = $this->overview->board();
        $t = $board['totals'];
        $todo = $t['toInvoiceCount'] + $t['draftsCount'] + $t['toSendCount'];

        $stats = [
            $this->statBox->toData([
                'label' => 'Da fare',
                'value' => (string) $todo,
                'comparison' => $todo === 0 ? 'tutto fatturato e inviato' : implode(', ', array_filter([
                    $t['toInvoiceCount'] > 0 ? $t['toInvoiceCount'] . ' da fatturare' : null,
                    $t['draftsCount'] > 0 ? F::plural($t['draftsCount'], 'bozza', 'bozze') : null,
                    $t['toSendCount'] > 0 ? $t['toSendCount'] . ' da inviare allo SdI' : null,
                ])),
                'feature' => true,
                'badge' => 'Adesso',
            ]),
            $this->statBox->toData([
                'label' => 'Da incassare',
                'value' => F::euro($t['unpaid']),
                'comparison' => $t['overdueCount'] > 0 ? F::plural($t['overdueCount'], 'fattura scaduta', 'fatture scadute') : F::plural($t['unpaidCount'], 'fattura emessa', 'fatture emesse'),
                'dotColor' => $t['overdueCount'] > 0 ? 'danger' : 'brass',
            ]),
            $this->statBox->toData([
                'label' => 'Fatturato ' . date('Y'),
                'value' => F::euro($t['issuedYear']),
                'comparison' => F::plural($t['issuedYearCount'], 'fattura', 'fatture') . ', IVA compresa',
                'dotColor' => 'moss',
            ]),
        ];

        return [$this->board->toData([
            'stats' => $stats,
            'groups' => $board['groups'],
            'recentPaid' => $board['recentPaid'],
            'links' => [
                ['label' => 'Nuova fattura', 'href' => '/fatture/nuovo', 'variant' => 'secondary'],
                ['label' => 'Dati azienda', 'href' => '/fatture/dati-azienda', 'variant' => 'ghost'],
            ],
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $t = $this->overview->board()['totals'];
        $parts = [];
        if ($t['toInvoiceCount'] > 0) {
            $parts[] = F::plural($t['toInvoiceCount'], 'incasso e\' da fatturare', 'incassi sono da fatturare');
        }
        if ($t['toSendCount'] > 0) {
            $parts[] = F::plural($t['toSendCount'], 'fattura va inviata', 'fatture vanno inviate') . ' allo SdI';
        }
        $parts[] = F::euro($t['unpaid']) . ' di fatture da incassare';

        return ucfirst(implode(', ', $parts)) . '.';
    }

    public function followUpSuggestions(): array
    {
        return ['Scadenzario', 'Clienti'];
    }
}
