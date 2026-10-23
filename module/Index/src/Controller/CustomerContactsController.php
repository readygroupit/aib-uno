<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditCustomerContactTool;
use App\Prompt\Tool\EditCustomerTool;
use App\Prompt\Tool\ListCustomersTool;
use App\Repository\CustomerContactRepository;
use App\Repository\CustomerRepository;

/**
 * Referenti di un cliente: stessa base delle altre entita', stessi
 * permessi dei clienti (fanno parte della sua scheda). Dopo il salvataggio
 * si torna alla scheda del cliente, dove i referenti si vedono.
 */
final class CustomerContactsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CustomerContactRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCustomerContactTool::class;
    }

    protected function listToolClass(): string
    {
        return ListCustomersTool::class;
    }

    protected function packageName(): string
    {
        return 'customers';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_contacts';
    }

    protected function listPageTitle(): string
    {
        return 'Clienti';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo referente';
    }

    protected function editPageTitle(): string
    {
        return 'Referente';
    }

    protected function defaultsOnCreate(): array
    {
        return ['is_decision_maker' => '0'];
    }

    protected function componentsAfterCreate(int $id): array
    {
        return $this->customerPage($id, 'Referente aggiunto.') ?? parent::componentsAfterCreate($id);
    }

    private function customerPage(int $contactId, string $message): ?array
    {
        $contact = $this->repository()->find($contactId);
        $customer = $contact !== null ? $this->container->get(CustomerRepository::class)->find((int) $contact->customerId) : null;
        if ($customer === null) {
            return null;
        }

        return $this->container->get(EditCustomerTool::class)->buildPage($customer, $message, 'success');
    }
}
