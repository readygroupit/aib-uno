<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Repository\WorkOrderDeliverableRepository;
use App\Repository\WorkOrderRepository;

final class EditWorkOrderDeliverableTool extends AbstractProcessChildTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderDeliverableRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_deliverables';
    }

    protected function entityTag(): string
    {
        return 'work_order_deliverable';
    }

    protected function baseUrl(): string
    {
        return '/commesse/consegne';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Cosa si consegna' : 'Cosa si consegna';
    }

    protected function hiddenFields(): array
    {
        return ['work_order_id'];
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
        return 'edit_work_order_deliverable';
    }

    public function description(): string
    {
        return "Apre una delle cose da consegnare di un progetto.";
    }
}
