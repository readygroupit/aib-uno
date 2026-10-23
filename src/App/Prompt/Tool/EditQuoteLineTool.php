<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\QuoteLineRepository;

final class EditQuoteLineTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return QuoteLineRepository::class;
    }

    protected function packageName(): string
    {
        return 'quotes';
    }

    protected function manifestEntityKey(): string
    {
        return 'quote_lines';
    }

    protected function entityTag(): string
    {
        return 'quote_line';
    }

    protected function baseUrl(): string
    {
        return '/preventivi/righe';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova riga del preventivo' : 'Riga del preventivo';
    }

    protected function hiddenFields(): array
    {
        return ['quote_id', 'position'];
    }

    protected function systemManagedFields(): array
    {
        return ['customer_service_id'];
    }

    protected function searchColumns(): array
    {
        return ['description'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'description', 'label' => 'Descrizione'], ['key' => 'unitPrice', 'label' => 'Prezzo']];
    }

    public function name(): string
    {
        return 'edit_quote_line';
    }

    public function description(): string
    {
        return "Aggiunge o modifica una riga di un preventivo.";
    }

    public function requiredPermission(): ?string
    {
        return 'quotes.edit';
    }
}
