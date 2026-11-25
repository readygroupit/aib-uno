<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Prompt\Tool\EditWorkOrderDeliverableTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\WorkOrderDeliverableRepository;

final class WorkOrderDeliverablesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderDeliverableRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderDeliverableTool::class;
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
        return 'work_order_deliverables';
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
        return ['is_done' => '0', 'position' => '99'];
    }

    protected function createdMessage(): string
    {
        return 'Aggiunto a cosa si consegna.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return $action === 'toggle' ? 'work_orders.edit' : parent::requiredPermissionForAction($action);
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $item = $this->repository()->find($id);
        if ($item instanceof \App\Model\WorkOrderDeliverable) {
            $this->repository()->update($id, ['done_at' => (int) $item->isDone === 1 ? ($item->doneAt ?? date('Y-m-d H:i:s')) : null]);
        }

        return null;
    }

    /** Pronto / non ancora, con un clic dalla pagina del progetto. */
    public function toggleAction(): void
    {
        $item = $this->repository()->find((int) $this->param('id'));
        if (!$item instanceof \App\Model\WorkOrderDeliverable) {
            $this->json(['ok' => false, 'message' => 'Non trovato.'], 404);

            return;
        }
        $done = (int) $item->isDone !== 1;
        $this->repository()->update((int) $item->id, ['is_done' => $done ? 1 : 0, 'done_at' => $done ? date('Y-m-d H:i:s') : null]);
        $this->json(['ok' => true, 'message' => $done ? "Pronto: \u{ab}{$item->label}\u{bb}." : "Da fare: \u{ab}{$item->label}\u{bb}."]);
    }
}
