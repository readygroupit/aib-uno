<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ProfilePermission;

final class ProfilePermissionRepository extends AbstractRepository
{
    protected string $table = 'profile_permissions';
    protected string $modelClass = ProfilePermission::class;
}
