<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditTaskTool;
use App\Prompt\Tool\ListTasksTool;
use App\Repository\TaskRepository;

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
        return 'Attivita';
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
        return ['stage' => 'da fare'];
    }

    protected function createdMessage(): string
    {
        return 'Attivita creata.';
    }
}
