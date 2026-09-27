<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\InvoiceRepository;

final class ListInvoicesTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return InvoiceRepository::class;
    }

    protected function entityTag(): string
    {
        return 'invoice';
    }

    protected function baseUrl(): string
    {
        return '/fatture';
    }

    protected function title(): string
    {
        return 'Fatture';
    }

    protected function statLabel(): string
    {
        return 'Totale fatture';
    }

    protected function createLabel(): ?string
    {
        return 'Nuova fattura';
    }

    protected function createPermission(): ?string
    {
        return 'invoices.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'invoiceNumber', 'label' => 'Numero'],
            ['key' => 'issueDate', 'label' => 'Emissione'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'total', 'label' => 'Totale'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/fatture/{id}', 'permission' => 'invoices.edit'],
        ];
    }

    protected function orderBy(): string
    {
        return 'issue_date DESC';
    }

    public function name(): string
    {
        return 'list_invoices';
    }

    public function description(): string
    {
        return "Mostra l'elenco delle fatture con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Fatture';
    }

    public function menuSection(): ?string
    {
        return 'vendite';
    }

    public function requiredPermission(): ?string
    {
        return 'invoices.view';
    }

    public function triggers(): array
    {
        return ['fatture', 'lista fatture', 'elenco fatture'];
    }
}
