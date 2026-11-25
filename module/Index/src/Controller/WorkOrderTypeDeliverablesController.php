<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditWorkOrderTypeDeliverableTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\WorkOrderTypeDeliverableRepository;

final class WorkOrderTypeDeliverablesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeDeliverableRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderTypeDeliverableTool::class;
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
        return 'work_order_type_deliverables';
    }

    protected function listPageTitle(): string
    {
        return 'Commesse';
    }

    protected function newPageTitle(): string
    {
        return 'Cosa si consegna';
    }

    protected function editPageTitle(): string
    {
        return 'Cosa si consegna';
    }

    protected function defaultsOnCreate(): array
    {
        return ['always_included' => '1'];
    }

    protected function createdMessage(): string
    {
        return 'Aggiunto a cosa si consegna.';
    }
}
