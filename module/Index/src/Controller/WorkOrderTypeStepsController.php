<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Prompt\Tool\EditWorkOrderTypeStepTool;
use App\Prompt\Tool\EditWorkOrderTypeTool;
use App\Prompt\Tool\ListWorkOrderTypesTool;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\WorkOrderTypeStepRepository;

/** Fasi di un tipo di commessa: dopo il salvataggio si torna al tipo, dove si vedono in ordine. */
final class WorkOrderTypeStepsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderTypeStepRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderTypeStepTool::class;
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
        return 'work_order_type_steps';
    }

    protected function listPageTitle(): string
    {
        return 'Tipi di commessa';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova fase';
    }

    protected function editPageTitle(): string
    {
        return 'Fase del modello';
    }

    protected function defaultsOnCreate(): array
    {
        return ['waits_for_client' => '0', 'offset_days' => '0'];
    }

    protected function componentsAfterCreate(int $id): array
    {
        return $this->typePage($id, 'Fase aggiunta.') ?? parent::componentsAfterCreate($id);
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        return 'Fase salvata. Vale per le prossime commesse di questo tipo.';
    }

    private function typePage(int $stepId, string $message): ?array
    {
        $step = $this->repository()->find($stepId);
        $type = $step !== null ? $this->container->get(WorkOrderTypeRepository::class)->find((int) $step->workOrderTypeId) : null;

        return $type !== null ? $this->container->get(EditWorkOrderTypeTool::class)->buildPage($type, $message, 'success') : null;
    }
}
