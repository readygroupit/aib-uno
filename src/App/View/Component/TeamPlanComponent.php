<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Il team: carico di oggi per persona, cosa sta facendo adesso, proposte per riequilibrare e la settimana a colori. Dati da App\Planner\PlannerOverviewService::team() e week().
 */
final class TeamPlanComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'team-plan'] + $config;
    }
}
