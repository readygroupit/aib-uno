<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Repository\WorkOrderTypeMilestoneRepository;
use App\Repository\WorkOrderTypeRepository;

final class EditWorkOrderTypeMilestoneTool extends AbstractProcessChildTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeMilestoneRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_type_milestones';
    }

    protected function entityTag(): string
    {
        return 'work_order_type_milestone';
    }

    protected function baseUrl(): string
    {
        return '/tipi-commessa/date';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova data chiave' : 'Data chiave del processo';
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

        return ['set_by_role_id' => $this->roleOptions($typeId), 'after_step_id' => $this->stepOptions($typeId)];
    }

    public function name(): string
    {
        return 'edit_work_order_type_milestone';
    }

    public function description(): string
    {
        return "Apre una data chiave di un processo (tipo di commessa).";
    }
}
