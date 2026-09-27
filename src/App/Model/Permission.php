<?php

declare(strict_types=1);

namespace App\Model;

final class Permission extends AbstractModel
{
    public ?int $permissionGroupId = null;
    public ?string $code = null;
    public ?string $name = null;
    public ?string $description = null;
    /** view/create/edit/delete - null per i permessi non ancora granulari (es. users.manage). */
    public ?string $category = null;
    public int $sortOrder = 0;
}
