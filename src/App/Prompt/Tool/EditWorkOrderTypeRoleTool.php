<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Repository\WorkOrderTypeRoleRepository;
use App\Repository\WorkOrderTypeRepository;

final class EditWorkOrderTypeRoleTool extends AbstractProcessChildTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeRoleRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_type_roles';
    }

    protected function entityTag(): string
    {
        return 'work_order_type_role';
    }

    protected function baseUrl(): string
    {
        return '/tipi-commessa/ruoli';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo ruolo' : 'Ruolo del processo';
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
        return [];
    }

    public function name(): string
    {
        return 'edit_work_order_type_role';
    }

    public function description(): string
    {
        return "Apre un ruolo di un processo (tipo di commessa).";
    }
}
