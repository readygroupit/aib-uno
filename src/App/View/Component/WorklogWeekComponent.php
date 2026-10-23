<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Il registro della settimana di una persona, giorno per giorno. Dati da App\Worklog\WorklogOverviewService::myWeek().
 */
final class WorklogWeekComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'worklog-week'] + $config;
    }
}
