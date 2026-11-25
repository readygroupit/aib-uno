<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\SeasonalEvent;

final class SeasonalEventRepository extends AbstractRepository
{
    protected string $table = 'seasonal_events';
    protected string $modelClass = SeasonalEvent::class;
}
