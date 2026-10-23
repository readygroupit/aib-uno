<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\Quote;
use App\Model\QuoteLine;
use App\Prompt\Tool\EditQuoteLineTool;
use App\Prompt\Tool\EditQuoteTool;
use App\Prompt\Tool\ShowQuotesTool;
use App\Quotes\QuoteService;
use App\Repository\QuoteLineRepository;
use App\Repository\QuoteRepository;

/**
 * Righe dei preventivi: dopo ogni salvataggio si ricalcolano i totali e si
 * torna al preventivo. Un preventivo gia' inviato non si tocca.
 */
final class QuoteLinesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return QuoteLineRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditQuoteLineTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowQuotesTool::class;
    }

    protected function packageName(): string
    {
        return 'quotes';
    }

    protected function manifestEntityKey(): string
    {
        return 'quote_lines';
    }

    protected function listPageTitle(): string
    {
        return 'Preventivi';
    }

    protected function newPageTitle(): string
    {
        return 'Riga del preventivo';
    }

    protected function editPageTitle(): string
    {
        return 'Riga del preventivo';
    }

    protected function defaultsOnCreate(): array
    {
        return ['quantity' => '1', 'billing_type' => 'una tantum', 'payment_plan' => 'unica soluzione'];
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return $action === 'index' ? 'quotes.view' : 'quotes.edit';
    }

    protected function crossFieldErrors(array $data): array
    {
        $quote = isset($data['quote_id']) ? $this->container->get(QuoteRepository::class)->find((int) $data['quote_id']) : null;
        if ($quote instanceof Quote && !$quote->isEditable()) {
            return ['description' => 'Il preventivo e\' gia\' stato inviato: non si modifica (fanne uno nuovo).'];
        }

        return [];
    }

    protected function componentsAfterCreate(int $id): array
    {
        return $this->quotePage($id, 'Riga aggiunta.') ?? parent::componentsAfterCreate($id);
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $line = $this->repository()->find($id);
        if ($line instanceof QuoteLine) {
            $this->container->get(QuoteService::class)->recalc((int) $line->quoteId);
        }

        return 'Riga aggiornata.';
    }

    protected function afterDelete(int $id): void
    {
        $line = $this->repository()->find($id, true);
        if ($line instanceof QuoteLine) {
            $this->container->get(QuoteService::class)->recalc((int) $line->quoteId);
        }
    }

    private function quotePage(int $lineId, string $message): ?array
    {
        $line = $this->repository()->find($lineId);
        if (!$line instanceof QuoteLine) {
            return null;
        }
        $this->container->get(QuoteService::class)->recalc((int) $line->quoteId);
        $quote = $this->container->get(QuoteRepository::class)->find((int) $line->quoteId);

        return $quote !== null ? $this->container->get(EditQuoteTool::class)->buildPage($quote, $message, 'success') : null;
    }
}
