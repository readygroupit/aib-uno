<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * La giornata di una persona in ordine: saluto, carico, avvisi, la scaletta (appuntamenti, viaggi, lavoro, pausa, tempo libero), quello che non ci sta, cosa aspetta il cliente, fatto oggi. Dati da App\Planner\PlannerOverviewService::myDay().
 */
final class DayPlanComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'day-plan'] + $config;
    }
}
