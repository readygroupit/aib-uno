<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ScheduledJobRun;

final class ScheduledJobRunRepository extends AbstractRepository
{
    protected string $table = 'scheduled_job_runs';
    protected string $modelClass = ScheduledJobRun::class;
}
