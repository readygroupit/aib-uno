<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Repository\WorkOrderChangeRequestRepository;
use App\Repository\WorkOrderRepository;

final class EditWorkOrderChangeRequestTool extends AbstractProcessChildTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderChangeRequestRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_change_requests';
    }

    protected function entityTag(): string
    {
        return 'work_order_change_request';
    }

    protected function baseUrl(): string
    {
        return '/commesse/modifiche';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Modifica chiesta dal cliente' : 'Modifica chiesta dal cliente';
    }

    protected function hiddenFields(): array
    {
        return ['work_order_id'];
    }

    protected function searchColumns(): array
    {
        return ['description'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'description', 'label' => 'Modifica'], ['key' => 'stage', 'label' => 'Stato']];
    }

    protected function parentPage(AbstractModel $model, string $message): ?array
    {
        $order = $this->container->get(WorkOrderRepository::class)->find((int) $model->workOrderId);

        return $order !== null ? $this->container->get(EditWorkOrderTool::class)->buildPage($order, $message, 'success') : null;
    }

    protected function dynamicOptions(AbstractModel $model): array
    {
        return [];
    }

    public function name(): string
    {
        return 'edit_work_order_change_request';
    }

    public function description(): string
    {
        return "Apre una modifica chiesta dal cliente su un progetto.";
    }
}
