<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Project;

final class ProjectRepository extends AbstractRepository
{
    protected string $table = 'projects';
    protected string $modelClass = Project::class;
}
