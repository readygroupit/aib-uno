<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Task;

final class TaskRepository extends AbstractRepository
{
    protected string $table = 'tasks';
    protected string $modelClass = Task::class;
}
