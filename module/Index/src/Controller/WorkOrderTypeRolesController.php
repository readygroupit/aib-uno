<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditWorkOrderTypeRoleTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\WorkOrderTypeRoleRepository;

final class WorkOrderTypeRolesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeRoleRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderTypeRoleTool::class;
    }

    protected function listToolClass(): string
    {
        return ListWorkOrdersTool::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_type_roles';
    }

    protected function listPageTitle(): string
    {
        return 'Commesse';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo ruolo';
    }

    protected function editPageTitle(): string
    {
        return 'Ruolo del processo';
    }

    protected function defaultsOnCreate(): array
    {
        return ['is_required' => '1', 'is_multiple' => '0'];
    }

    protected function createdMessage(): string
    {
        return 'Ruolo aggiunto.';
    }
}
