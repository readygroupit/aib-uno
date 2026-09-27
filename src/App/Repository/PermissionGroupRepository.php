<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\PermissionGroup;

final class PermissionGroupRepository extends AbstractRepository
{
    protected string $table = 'permission_groups';
    protected string $modelClass = PermissionGroup::class;
}
