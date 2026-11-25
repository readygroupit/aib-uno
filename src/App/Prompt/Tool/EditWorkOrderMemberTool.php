<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Repository\WorkOrderMemberRepository;
use App\Repository\WorkOrderRepository;

final class EditWorkOrderMemberTool extends AbstractProcessChildTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderMemberRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_members';
    }

    protected function entityTag(): string
    {
        return 'work_order_member';
    }

    protected function baseUrl(): string
    {
        return '/commesse/squadra';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Aggiungi alla squadra' : 'Nella squadra';
    }

    protected function hiddenFields(): array
    {
        return ['work_order_id'];
    }

    protected function searchColumns(): array
    {
        return ['notes'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'notes', 'label' => 'Note']];
    }

    protected function parentPage(AbstractModel $model, string $message): ?array
    {
        $order = $this->container->get(WorkOrderRepository::class)->find((int) $model->workOrderId);

        return $order !== null ? $this->container->get(EditWorkOrderTool::class)->buildPage($order, $message, 'success') : null;
    }

    protected function dynamicOptions(AbstractModel $model): array
    {
        $order = $model->workOrderId !== null ? $this->container->get(WorkOrderRepository::class)->find((int) $model->workOrderId) : null;

        return ['role_id' => $this->roleOptions($order?->workOrderTypeId !== null ? (int) $order->workOrderTypeId : null)];
    }

    public function name(): string
    {
        return 'edit_work_order_member';
    }

    public function description(): string
    {
        return "Apre una persona della squadra di un progetto.";
    }
}
