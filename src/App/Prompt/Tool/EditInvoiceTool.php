<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\InvoiceRepository;

final class EditInvoiceTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return InvoiceRepository::class;
    }

    protected function packageName(): string
    {
        return 'invoices';
    }

    protected function entityTag(): string
    {
        return 'invoice';
    }

    protected function baseUrl(): string
    {
        return '/fatture';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova fattura' : 'Modifica fattura';
    }

    protected function searchColumns(): array
    {
        return ['invoice_number', 'description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'invoiceNumber', 'label' => 'Numero'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    public function name(): string
    {
        return 'edit_invoice';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di una fattura, cercata per id o per numero/descrizione. "
            . "Usalo quando l'utente chiede di aprire o modificare una specifica fattura.";
    }

    public function requiredPermission(): ?string
    {
        return 'invoices.edit';
    }
}
