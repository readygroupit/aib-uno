<?php

declare(strict_types=1);

namespace App\Model;

final class ConfigGroup extends AbstractModel
{
    public ?int $parentId = null;
    public ?string $code = null;
    public ?string $name = null;
    public ?string $description = null;
    public int $sortOrder = 0;
}
