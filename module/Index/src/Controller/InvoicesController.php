<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditInvoiceTool;
use App\Prompt\Tool\ListInvoicesTool;
use App\Repository\InvoiceRepository;

final class InvoicesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return InvoiceRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditInvoiceTool::class;
    }

    protected function listToolClass(): string
    {
        return ListInvoicesTool::class;
    }

    protected function packageName(): string
    {
        return 'invoices';
    }

    protected function listPageTitle(): string
    {
        return 'Fatture';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova fattura';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica fattura';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'bozza'];
    }

    protected function createdMessage(): string
    {
        return 'Fattura creata.';
    }
}
