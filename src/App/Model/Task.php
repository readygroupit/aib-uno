<?php

declare(strict_types=1);

namespace App\Model;

final class Task extends AbstractModel
{
    public ?string $title = null;
    public ?string $description = null;
    public ?string $dueAt = null;
    public ?string $stage = null;
    public ?int $assignedUserId = null;
    public ?string $completedAt = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
}
