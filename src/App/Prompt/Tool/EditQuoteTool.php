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

    public function __construct(Container $container)
    {
        parent::__construct($container);
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

        return [$this->summary->toData(['url' => '/preventivi/' . $model->id, 'quote' => $this->overview->detail($model, $this->auth->hasPermission('quotes.approve'))])];
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
