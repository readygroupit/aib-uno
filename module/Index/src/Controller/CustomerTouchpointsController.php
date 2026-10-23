<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\CustomerTouchpoint;
use App\Package\PackageCatalogService;
use App\Prompt\Tool\EditCustomerTool;
use App\Prompt\Tool\EditCustomerTouchpointTool;
use App\Prompt\Tool\ShowClientHealthTool;
use App\Repository\CustomerRepository;
use App\Repository\CustomerTouchpointRepository;
use App\Repository\TaskRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;

/**
 * Registro dei contatti con i clienti. Un prossimo passo con una data
 * diventa un'attivita' di chi ha sentito il cliente; dopo il salvataggio
 * si torna alla scheda del cliente, con la temperatura aggiornata.
 */
final class CustomerTouchpointsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CustomerTouchpointRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCustomerTouchpointTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowClientHealthTool::class;
    }

    protected function packageName(): string
    {
        return 'client_health';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_touchpoints';
    }

    protected function listPageTitle(): string
    {
        return 'Clienti a rischio';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo contatto';
    }

    protected function editPageTitle(): string
    {
        return 'Contatto';
    }

    protected function defaultsOnCreate(): array
    {
        return [
            'contact_date' => date('Y-m-d'),
            'channel' => 'telefono',
            'mood' => 'neutro',
            'user_id' => (string) $this->container->get(AuthService::class)->currentUserId(),
        ];
    }

    protected function afterCreate(int $id): void
    {
        $touchpoint = $this->repository()->find($id);
        if (!$touchpoint instanceof CustomerTouchpoint || $touchpoint->nextDate === null || trim((string) $touchpoint->nextStep) === '' || !in_array('tasks', PackageCatalogService::installedNames(), true)) {
            return;
        }
        $customer = $this->container->get(CustomerRepository::class)->find((int) $touchpoint->customerId);
        $this->container->get(TaskRepository::class)->insert([
            'title' => ($customer !== null ? $customer->displayName() . ': ' : '') . $touchpoint->nextStep,
            'description' => $touchpoint->summary,
            'planned_date' => $touchpoint->nextDate,
            'due_at' => $touchpoint->nextDate . ' 18:00:00',
            'assigned_user_id' => $touchpoint->userId ?? $this->container->get(AuthService::class)->currentUserId(),
            'stage' => 'da fare',
            'priority' => in_array($touchpoint->mood, ['freddo', 'scontento'], true) ? 'alta' : 'normale',
            'estimated_minutes' => 20,
            'waits_for_client' => 0,
            'entity_type' => 'customers',
            'entity_id' => $touchpoint->customerId,
        ]);
        $this->taskCreated = $touchpoint->nextDate;
    }

    private ?string $taskCreated = null;

    protected function componentsAfterCreate(int $id): array
    {
        $touchpoint = $this->repository()->find($id);
        $customer = $touchpoint !== null ? $this->container->get(CustomerRepository::class)->find((int) $touchpoint->customerId) : null;
        $message = 'Contatto registrato.' . ($this->taskCreated !== null ? ' Il prossimo passo e\' nelle tue attivita\' per ' . F::shortDate($this->taskCreated, date('Y-m-d')) . '.' : '');
        if ($customer === null) {
            return parent::componentsAfterCreate($id);
        }

        return $this->container->get(EditCustomerTool::class)->buildPage($customer, $message, 'success');
    }
}
