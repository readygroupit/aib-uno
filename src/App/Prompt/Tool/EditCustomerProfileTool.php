<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Repository\CustomerRepository;
use App\Repository\CustomerProfileRepository;
use App\Repository\WorkOrderRepository;

/** Dopo il salvataggio si torna al cliente (o al progetto), dove si vede insieme al resto di quello che sappiamo. */
final class EditCustomerProfileTool extends AbstractEditEntityTool
{
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
    }

    protected function repositoryClass(): string
    {
        return CustomerProfileRepository::class;
    }

    protected function packageName(): string
    {
        return 'client_knowledge';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_profiles';
    }

    protected function entityTag(): string
    {
        return 'customer_profile';
    }

    protected function baseUrl(): string
    {
        return '/clienti/profilo';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Cosa sappiamo del cliente' : 'Cosa sappiamo del cliente';
    }

    protected function searchColumns(): array
    {
        return ['history', 'products'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'customerId', 'label' => 'Cliente']];
    }

    protected function hiddenFields(): array
    {
        return ['customer_id'];
    }

    protected function systemManagedFields(): array
    {
        return ['author_user_id', 'last_source'];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        if ($model->id !== null && $message !== null && $fieldErrors === [] && $messageType !== 'error') {
            $orderId = property_exists($model, 'workOrderId') ? $model->workOrderId : null;
            if ($orderId !== null && ($order = $this->container->get(WorkOrderRepository::class)->find((int) $orderId)) !== null) {
                return $this->container->get(EditWorkOrderTool::class)->buildPage($order, $message, 'success');
            }
            $customer = $this->container->get(CustomerRepository::class)->find((int) $model->customerId);
            if ($customer !== null) {
                return $this->container->get(EditCustomerTool::class)->buildPage($customer, $message, 'success');
            }
        }

        return parent::buildPage($model, $message, $messageType, $fieldErrors);
    }

    public function name(): string
    {
        return 'edit_customer_profile';
    }

    public function description(): string
    {
        return "Apre il profilo di un cliente: storia, mission, pubblico, concorrenti, stile, stagionalita'.";
    }

    public function requiredPermission(): ?string
    {
        return 'client_knowledge.edit';
    }
}
