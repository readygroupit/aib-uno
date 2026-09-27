<?php

declare(strict_types=1);

namespace App\Model;

final class ScheduledJobRun extends AbstractModel
{
    public ?int $scheduledJobId = null;
    public ?string $startedAt = null;
    public ?string $finishedAt = null;
    public ?string $runStatus = null;
    public ?string $result = null;
}
