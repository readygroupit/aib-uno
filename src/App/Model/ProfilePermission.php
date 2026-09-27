<?php

declare(strict_types=1);

namespace App\Model;

final class ProfilePermission extends AbstractModel
{
    public ?int $profileId = null;
    public ?int $permissionId = null;
}
