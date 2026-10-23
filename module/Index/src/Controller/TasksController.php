<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\Task;
use App\Prompt\Tool\EditTaskTool;
use App\Prompt\Tool\ListTasksTool;
use App\Repository\TaskRepository;
use App\Support\ItalianFormat as F;
use App\Work\TaskService;

final class TasksController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return TaskRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditTaskTool::class;
    }

    protected function listToolClass(): string
    {
        return ListTasksTool::class;
    }

    protected function packageName(): string
    {
        return 'tasks';
    }

    protected function listPageTitle(): string
    {
        return "Attivita'";
    }

    protected function newPageTitle(): string
    {
        return 'Nuova attivita';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica attivita';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'da fare', 'priority' => 'normale', 'planned_date' => date('Y-m-d'), 'waits_for_client' => '0'];
    }

    protected function createdMessage(): string
    {
        return "Attivita' creata.";
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return $action === 'toggle' ? 'tasks.edit' : parent::requiredPermissionForAction($action);
    }

    /** Le mie, oppure ?tutte=1 per quelle di tutto il team. */
    public function indexAction(): ?string
    {
        $scope = $this->request->get('tutte') ? 'tutte' : 'mie';
        $components = $this->container->get(ListTasksTool::class)->execute(['scope' => $scope]);

        return $this->renderPage($components, ['title' => $this->listPageTitle()]);
    }

    /** Una fase di commessa nuova o cambiata riallinea la commessa (stato, attese). */
    protected function afterCreate(int $id): void
    {
        $task = $this->repository()->find($id);
        if ($task instanceof Task) {
            $this->container->get(TaskService::class)->afterChange($task);
        }
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $this->afterCreate($id);

        return null;
    }

    /** Fatta / da rifare, con un clic dalle liste. */
    public function toggleAction(): void
    {
        $task = $this->repository()->find((int) $this->param('id'));
        if (!$task instanceof Task) {
            $this->json(['ok' => false, 'message' => "Attivita' non trovata."], 404);

            return;
        }

        $service = $this->container->get(TaskService::class);
        if (!$task->isOpen()) {
            $service->reopen((int) $task->id);
            $this->json(['ok' => true, 'message' => "Riaperta: \u{ab}{$task->title}\u{bb}."]);

            return;
        }

        $minutes = (int) $this->request->get('minutes', 0);
        $next = $service->complete((int) $task->id, $minutes > 0 ? $minutes : null);
        $message = "Fatta: \u{ab}{$task->title}\u{bb}.";
        if ($next !== null) {
            $message .= " La prossima e' " . F::relativeDays((string) $next->plannedDate, date('Y-m-d')) . '.';
        }
        $this->json(['ok' => true, 'message' => $message]);
    }
}
