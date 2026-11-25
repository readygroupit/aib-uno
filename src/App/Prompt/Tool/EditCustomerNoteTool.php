<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Repository\CustomerRepository;
use App\Repository\CustomerNoteRepository;
use App\Repository\WorkOrderRepository;

/** Dopo il salvataggio si torna al cliente (o al progetto), dove si vede insieme al resto di quello che sappiamo. */
final class EditCustomerNoteTool extends AbstractEditEntityTool
{
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
    }

    protected function repositoryClass(): string
    {
        return CustomerNoteRepository::class;
    }

    protected function packageName(): string
    {
        return 'client_knowledge';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_notes';
    }

    protected function entityTag(): string
    {
        return 'customer_note';
    }

    protected function baseUrl(): string
    {
        return '/clienti/diario';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nel diario del cliente' : 'Nel diario del cliente';
    }

    protected function searchColumns(): array
    {
        return ['title', 'body'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'title', 'label' => 'Nota'], ['key' => 'kind', 'label' => 'Cos\'e\''], ['key' => 'notedAt', 'label' => 'Quando']];
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
        return 'edit_customer_note';
    }

    public function description(): string
    {
        return "Apre un'idea, una decisione o una nota del diario di un cliente (anche nella roadmap).";
    }

    public function requiredPermission(): ?string
    {
        return 'client_knowledge.edit';
    }
}
