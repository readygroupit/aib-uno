<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * La settimana di una persona, giorno per giorno, con la scaletta di ogni giorno. Dati da App\Planner\PlannerOverviewService::personWeek().
 */
final class WeekPlanComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'week-plan'] + $config;
    }
}
