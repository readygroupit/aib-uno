<?php

declare(strict_types=1);

namespace App\Model;

final class ScheduledJob extends AbstractModel
{
    public ?string $jobType = null;
    public ?string $payload = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
    public ?string $runAt = null;
    public ?int $intervalMinutes = null;
    public ?string $lastRunAt = null;
    public ?string $lastRunStatus = null;
}
