<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Prompt\Tool\EditCustomerTeamMemberTool;
use App\Prompt\Tool\EditCustomerTool;
use App\Prompt\Tool\ListCustomersTool;
use App\Repository\CustomerRepository;
use App\Repository\CustomerTeamMemberRepository;

/**
 * La squadra interna di un cliente: stessi permessi dei clienti. Dopo il
 * salvataggio si torna alla scheda del cliente, dove la squadra si vede.
 */
final class CustomerTeamMembersController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CustomerTeamMemberRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCustomerTeamMemberTool::class;
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
        return 'customer_team_members';
    }

    protected function listPageTitle(): string
    {
        return 'Clienti';
    }

    protected function newPageTitle(): string
    {
        return 'Squadra del cliente';
    }

    protected function editPageTitle(): string
    {
        return 'Squadra del cliente';
    }

    protected function defaultsOnCreate(): array
    {
        return ['role' => 'social'];
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return $action === 'index' ? 'customers.view' : 'customers.edit';
    }

    protected function componentsAfterCreate(int $id): array
    {
        return $this->customerPage($id, 'Aggiunto alla squadra del cliente.') ?? parent::componentsAfterCreate($id);
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        return 'Squadra aggiornata.';
    }

    private function customerPage(int $memberId, string $message): ?array
    {
        $member = $this->repository()->find($memberId);
        $customer = $member !== null ? $this->container->get(CustomerRepository::class)->find((int) $member->customerId) : null;

        return $customer !== null ? $this->container->get(EditCustomerTool::class)->buildPage($customer, $message, 'success') : null;
    }
}
