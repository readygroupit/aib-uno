<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditCustomerTool;
use App\Prompt\Tool\ListCustomersTool;
use App\Repository\CustomerRepository;

/**
 * Primo vero utilizzo di AbstractEntityController: solo i dati
 * specifici di 'customers' - index/new/edit e la validazione sono
 * ereditati.
 */
final class CustomersController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CustomerRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCustomerTool::class;
    }

    protected function listToolClass(): string
    {
        return ListCustomersTool::class;
    }

    protected function packageName(): string
    {
        return 'customers';
    }

    protected function listPageTitle(): string
    {
        return 'Clienti';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo cliente';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica cliente';
    }

    protected function createdMessage(): string
    {
        return 'Cliente creato.';
    }
}
