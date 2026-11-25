<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Prompt\Tool\ListCustomersTool;
use App\Prompt\Tool\EditCustomerProfileTool;
use App\Repository\CustomerProfileRepository;

final class CustomerProfilesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CustomerProfileRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCustomerProfileTool::class;
    }

    protected function listToolClass(): string
    {
        return ListCustomersTool::class;
    }

    protected function packageName(): string
    {
        return 'client_knowledge';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_profiles';
    }

    protected function listPageTitle(): string
    {
        return 'Clienti';
    }

    protected function newPageTitle(): string
    {
        return 'Cosa sappiamo del cliente';
    }

    protected function editPageTitle(): string
    {
        return 'Cosa sappiamo del cliente';
    }

    protected function defaultsOnCreate(): array
    {
        return [];
    }

    protected function createdMessage(): string
    {
        return "Salvato: l'assistente ne terra' conto.";
    }

    /** Un profilo per cliente: se c'e' gia', si apre quello. */
    public function newAction(): ?string
    {
        $customerId = (int) $this->request->get('customer_id', 0);
        $existing = $customerId > 0 ? $this->container->get(CustomerProfileRepository::class)->findByCustomer($customerId) : null;
        if ($existing !== null && $this->request->getMethod() !== 'POST') {
            $this->redirect('/clienti/profilo/' . $existing->id);

            return null;
        }

        return parent::newAction();
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $this->repository()->update($id, ['last_source' => 'a mano del ' . date('d/m/Y')]);

        return null;
    }
}
