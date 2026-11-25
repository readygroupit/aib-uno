<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\Quote;
use App\Quotes\QuoteOverviewService;
use App\Repository\QuoteRepository;
use App\View\Component\QuoteSummaryComponent;

final class EditQuoteTool extends AbstractEditEntityTool
{
    private QuoteOverviewService $overview;
    private QuoteSummaryComponent $summary;
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->overview = $container->get(QuoteOverviewService::class);
        $this->summary = $container->get(QuoteSummaryComponent::class);
    }

    protected function repositoryClass(): string
    {
        return QuoteRepository::class;
    }

    protected function packageName(): string
    {
        return 'quotes';
    }

    protected function entityTag(): string
    {
        return 'quote';
    }

    protected function baseUrl(): string
    {
        return '/preventivi';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo preventivo' : 'Modifica preventivo';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Dati del preventivo';
    }

    protected function systemManagedFields(): array
    {
        return ['quote_number', 'number_year', 'number_seq', 'stage', 'total_one_off', 'total_yearly', 'approved_by', 'approved_at', 'sent_at', 'accepted_at', 'accepted_via', 'rejected_reason'];
    }

    protected function searchColumns(): array
    {
        return ['title', 'quote_number'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'quoteNumber', 'label' => 'Numero'], ['key' => 'title', 'label' => 'Oggetto'], ['key' => 'stage', 'label' => 'Stato']];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof Quote) {
            return [];
        }

        $components = [$this->summary->toData(['url' => '/preventivi/' . $model->id, 'quote' => $this->overview->detail($model, $this->auth->hasPermission('quotes.approve'))])];
        // Cosa ha chiesto il cliente (raccolta informazioni del progetto del preventivo).
        if (\App\Knowledge\KnowledgeService::isInstalled() && in_array('work_orders', \App\Package\PackageCatalogService::installedNames(), true)) {
            $order = $this->container->get(\App\Repository\WorkOrderRepository::class)->findByQuote((int) $model->id);
            $brief = $order !== null ? $this->container->get(\App\Knowledge\KnowledgeService::class)->quoteBrief((int) $order->id) : null;
            if ($brief !== null) {
                $components[] = $brief;
            }
        }

        return $components;
    }

    /**
     * Il preventivo: in testa stato, cliente, azioni e cosa manca; sotto
     * le schede "Righe" (si apre per prima: righe, totali e quello che
     * serve per fare il prezzo) e "Dati del preventivo" (il form).
     */
    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        if (!$model instanceof Quote || $model->id === null) {
            return parent::buildPage($model, $message, $messageType, $fieldErrors);
        }
        $detail = $this->detailComponents($model);
        $head = array_shift($detail);
        $head['guide'] = [
            'title' => 'Il preventivo',
            'text' => "In alto lo stato e le azioni del momento (chiedi approvazione, approva, segna inviato...). \"Prima di mandarlo manca\" dice cosa completare.",
            'steps' => ['Righe: le voci e i totali.', 'Dati del preventivo: cliente, date, pagamento, sconto, testi.', 'Quando e\' pronto: chiedi l\'approvazione, poi segnalo come inviato.'],
            'tip' => 'Le fasi del progetto legate al preventivo si chiudono da sole a ogni passo.',
        ];
        // Il progetto del preventivo: per tornarci.
        if (in_array('work_orders', \App\Package\PackageCatalogService::installedNames(), true)) {
            $order = $this->container->get(\App\Repository\WorkOrderRepository::class)->findByQuote((int) $model->id);
            if ($order !== null) {
                $head['project'] = ['label' => 'Progetto: ' . $order->title, 'href' => '/commesse/' . $order->id];
            }
        }
        $quote = $head;
        $lines = [
            'type' => 'quote-lines',
            'lines' => $quote['lines'],
            'totals' => $quote['totals'],
            'discount' => $quote['discount'],
            'editable' => $quote['editable'],
            'newLineHref' => $quote['newLineHref'],
            'guide' => [
                'text' => "Le voci del preventivo, dal listino o scritte a mano. Il prezzo del listino e' solo indicativo: qui si mette quello vero per questo cliente.",
                'steps' => ['"+ Riga" aggiunge una voce: scegli il servizio dal listino, il prezzo parte da quello indicativo.', 'Clicca una riga per cambiarla (finche\' il preventivo non e\' approvato).', 'I totali si aggiornano da soli; lo sconto si mette in "Dati del preventivo".'],
                'tip' => 'Sotto le righe trovi cosa ha chiesto il cliente, se il commerciale l\'ha raccolto.',
            ],
        ];

        return $this->tabbedPage($model, $head, [
            ['key' => 'righe', 'label' => 'Righe', 'icon' => 'list-check', 'color' => 'brass', 'count' => count($quote['lines']) ?: null, 'components' => [$lines, ...$detail]],
        ], $message, $messageType, $fieldErrors, ['key' => 'dati', 'label' => 'Dati del preventivo', 'short' => 'Dati']);
    }

    public function name(): string
    {
        return 'edit_quote';
    }

    public function description(): string
    {
        return "Apre o crea un preventivo (cercato per numero, oggetto o id): righe dal listino, approvazione, invio, accettazione.";
    }

    public function requiredPermission(): ?string
    {
        return 'quotes.edit';
    }
}
