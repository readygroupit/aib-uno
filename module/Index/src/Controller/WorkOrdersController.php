<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\WorkOrder;
use App\Prompt\Tool\EditWorkOrderTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\TaskRepository;
use App\Repository\WorkOrderRepository;
use App\Support\ItalianFormat as F;
use App\Work\TaskService;
use App\Work\WorkOrderService;

/**
 * Commesse. Alla creazione le fasi del tipo diventano attivita'; le azioni
 * con un clic (attesa del cliente, sollecitato, ripresa, pausa, consegna)
 * rispondono con la scheda aggiornata.
 */
final class WorkOrdersController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderTool::class;
    }

    protected function listToolClass(): string
    {
        return ListWorkOrdersTool::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function listPageTitle(): string
    {
        return 'Commesse';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova commessa';
    }

    protected function editPageTitle(): string
    {
        return 'Commessa';
    }

    protected function defaultsOnCreate(): array
    {
        return [
            'stage' => 'da avviare',
            'priority' => 'normale',
            'start_date' => date('Y-m-d'),
            'owner_user_id' => $this->container->get(\App\Service\AuthService::class)->currentUserId(),
        ];
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return in_array($action, ['wait', 'resume', 'pause', 'deliver', 'reminded'], true)
            ? 'work_orders.edit'
            : parent::requiredPermissionForAction($action);
    }

    protected function afterCreate(int $id): void
    {
        $this->service()->createStepsFromType($id);
    }

    protected function componentsAfterCreate(int $id): array
    {
        $steps = count($this->container->get(TaskRepository::class)->findForEntity(WorkOrderService::ENTITY, $id));
        $message = $steps > 0
            ? 'Commessa creata con ' . F::plural($steps, 'fase', 'fasi') . ' dal modello: la prima e\' gia\' nelle attivita\'.'
            : 'Commessa creata. Aggiungi le fasi con "+ Fase".';

        return $this->container->get(EditWorkOrderTool::class)->buildPage($this->repository()->find($id), $message, 'success');
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $created = 0;
        if ($before instanceof WorkOrder && $before->workOrderTypeId === null) {
            $created = $this->service()->createStepsFromType($id);
        }
        $this->service()->refresh($id);

        return $created > 0 ? 'Salvato: aggiunte ' . F::plural($created, 'fase', 'fasi') . ' dal modello.' : null;
    }

    public function waitAction(): ?string
    {
        $id = (int) $this->param('id');
        $reason = trim((string) $this->request->get('reason', ''));
        $this->service()->setWaiting($id, $reason);

        return $this->reply($id, 'Segnata in attesa del cliente.');
    }

    /**
     * "Il cliente ha risposto": se la commessa era ferma su una fase che
     * aspettava il cliente, quella fase e' fatta e si passa alla prossima.
     */
    public function resumeAction(): ?string
    {
        $id = (int) $this->param('id');
        $tasks = $this->container->get(TaskRepository::class)->findForEntity(WorkOrderService::ENTITY, $id);
        $next = $this->service()->nextStep($tasks);

        if ($next !== null && (int) $next->waitsForClient === 1) {
            $this->container->get(TaskService::class)->complete((int) $next->id);
            $after = $this->service()->nextStep($this->container->get(TaskRepository::class)->findForEntity(WorkOrderService::ENTITY, $id));
            $this->service()->resume($id);
            $this->service()->refresh($id);

            return $this->reply($id, "Fatto: \u{ab}{$next->title}\u{bb}." . ($after !== null ? " Ora tocca a \u{ab}{$after->title}\u{bb}." : ''));
        }

        $this->service()->resume($id);

        return $this->reply($id, 'Ripresa: di nuovo in corso.');
    }

    public function pauseAction(): ?string
    {
        $id = (int) $this->param('id');
        $this->service()->pause($id);

        return $this->reply($id, 'Messa in pausa: non comparira\' tra le commesse ferme.');
    }

    public function deliverAction(): ?string
    {
        $id = (int) $this->param('id');
        $this->service()->deliver($id);

        return $this->reply($id, 'Consegnata. Bel lavoro!');
    }

    public function remindedAction(): ?string
    {
        $id = (int) $this->param('id');
        $this->service()->markReminded($id);

        return $this->reply($id, 'Segnato: cliente sollecitato oggi.');
    }

    private function reply(int $id, string $message): ?string
    {
        return $this->renderPage(
            $this->container->get(EditWorkOrderTool::class)->buildPage($this->repository()->find($id), $message, 'success'),
            ['title' => $this->editPageTitle()]
        );
    }

    private function service(): WorkOrderService
    {
        return $this->container->get(WorkOrderService::class);
    }
}
