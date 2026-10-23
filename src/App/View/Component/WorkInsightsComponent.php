<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Pannello dei titolari: ore segnate, chi non ha risposto, tempi medi per tipo di lavoro con lo scarto, casi da guardare, attivita' ferme, chi e' oltre il carico. Dati da WorklogOverviewService::insights().
 */
final class WorkInsightsComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'work-insights'] + $config;
    }
}
