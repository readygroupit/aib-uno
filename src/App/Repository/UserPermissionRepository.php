<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\UserPermission;

final class UserPermissionRepository extends AbstractRepository
{
    protected string $table = 'user_permissions';
    protected string $modelClass = UserPermission::class;
}
