<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\WorkEntry;
use App\Prompt\Tool\EditWorkEntryTool;
use App\Prompt\Tool\ShowWorklogTool;
use App\Repository\TaskRepository;
use App\Repository\WorkEntryRepository;
use App\Service\AuthService;

/**
 * Voci del registro, a mano: aggiungere qualcosa fuori programma o
 * correggere un tempo. Ognuno vede e tocca solo le proprie.
 */
final class WorkEntriesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkEntryRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkEntryTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowWorklogTool::class;
    }

    protected function packageName(): string
    {
        return 'worklog';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_entries';
    }

    protected function listPageTitle(): string
    {
        return 'Il mio registro';
    }

    protected function newPageTitle(): string
    {
        return 'Aggiungi al registro';
    }

    protected function editPageTitle(): string
    {
        return 'Voce del registro';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return 'worklog.view';
    }

    protected function defaultsOnCreate(): array
    {
        return ['user_id' => $this->me(), 'work_date' => date('Y-m-d'), 'source' => 'manuale'];
    }

    public function editAction(): ?string
    {
        $this->guardOwnership();

        return parent::editAction();
    }

    public function deleteAction(): ?string
    {
        $this->guardOwnership();

        return parent::deleteAction();
    }

    protected function crossFieldErrors(array $data): array
    {
        // La propria voce, mai a nome di un altro.
        if (isset($data['user_id']) && (int) $data['user_id'] !== $this->me()) {
            return ['user_id' => 'Puoi segnare solo il tuo lavoro.'];
        }

        return [];
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $this->syncTaskTime($id);

        return null;
    }

    protected function afterDelete(int $id): void
    {
        $this->syncTaskTime($id);
    }

    private function syncTaskTime(int $id): void
    {
        $entry = $this->repository()->find($id, true);
        if ($entry instanceof WorkEntry && $entry->taskId !== null) {
            $this->container->get(TaskRepository::class)->update((int) $entry->taskId, [
                'actual_minutes' => $this->container->get(WorkEntryRepository::class)->minutesForTask((int) $entry->taskId),
            ]);
        }
    }

    private function guardOwnership(): void
    {
        $entry = $this->repository()->find((int) $this->param('id'), true);
        if ($entry instanceof WorkEntry && (int) $entry->userId !== $this->me()) {
            throw new \App\Core\Exception\ForbiddenException('Voce di un altro registro.');
        }
    }

    private function me(): int
    {
        return (int) $this->container->get(AuthService::class)->currentUserId();
    }
}
