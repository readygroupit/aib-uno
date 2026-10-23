<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditWorkOrderTypeTool;
use App\Prompt\Tool\ListWorkOrderTypesTool;
use App\Repository\WorkOrderTypeRepository;

final class WorkOrderTypesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderTypeTool::class;
    }

    protected function listToolClass(): string
    {
        return ListWorkOrderTypesTool::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_types';
    }

    protected function listPageTitle(): string
    {
        return 'Tipi di commessa';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo tipo di commessa';
    }

    protected function editPageTitle(): string
    {
        return 'Tipo di commessa';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'in uso'];
    }

    protected function createdMessage(): string
    {
        return 'Tipo creato: ora aggiungi le fasi.';
    }
}
