<?php

declare(strict_types=1);

namespace App\Model;

final class WorkCheckin extends AbstractModel
{
    public ?int $userId = null;
    public ?string $checkedAt = null;
    public ?string $period = null;
}
