<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\WorkOrderTypeStepRepository;

final class EditWorkOrderTypeStepTool extends AbstractProcessChildTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeStepRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_type_steps';
    }

    protected function entityTag(): string
    {
        return 'work_order_type_step';
    }

    protected function baseUrl(): string
    {
        return '/tipi-commessa/fasi';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova fase' : 'Fase del modello';
    }

    protected function searchColumns(): array
    {
        return ['title'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'title', 'label' => 'Fase'], ['key' => 'position', 'label' => 'Ordine']];
    }

    protected function hiddenFields(): array
    {
        return ['work_order_type_id'];
    }

    protected function parentPage(AbstractModel $model, string $message): ?array
    {
        $type = $this->container->get(WorkOrderTypeRepository::class)->find((int) $model->workOrderTypeId);

        return $type !== null ? $this->container->get(EditWorkOrderTypeTool::class)->buildPage($type, $message, 'success') : null;
    }

    protected function dynamicOptions(AbstractModel $model): array
    {
        $typeId = $model->workOrderTypeId !== null ? (int) $model->workOrderTypeId : null;

        return [
            'role_id' => $this->roleOptions($typeId),
            'milestone_id' => $this->milestoneOptions($typeId),
            'reject_step_id' => $this->stepOptions($typeId, $model->id !== null ? (int) $model->id : null),
        ];
    }

    public function name(): string
    {
        return 'edit_work_order_type_step';
    }

    public function description(): string
    {
        return "Apre una fase di un tipo di commessa.";
    }
}
