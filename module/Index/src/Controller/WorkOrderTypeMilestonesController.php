<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditWorkOrderTypeMilestoneTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\WorkOrderTypeMilestoneRepository;

final class WorkOrderTypeMilestonesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeMilestoneRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderTypeMilestoneTool::class;
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
        return 'work_order_type_milestones';
    }

    protected function listPageTitle(): string
    {
        return 'Commesse';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova data chiave';
    }

    protected function editPageTitle(): string
    {
        return 'Data chiave del processo';
    }

    protected function defaultsOnCreate(): array
    {
        return ['creates_appointment' => '1'];
    }

    protected function createdMessage(): string
    {
        return 'Data chiave aggiunta.';
    }
}
