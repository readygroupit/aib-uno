<?php

declare(strict_types=1);

namespace App\Model;

final class WorkEntry extends AbstractModel
{
    public ?int $userId = null;
    public ?string $workDate = null;
    public ?int $minutes = null;
    public ?string $title = null;
    public ?int $taskId = null;
    public ?int $customerId = null;
    public ?string $source = null;
    public ?string $notes = null;
}
