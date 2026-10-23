<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Attivita' per giorno (in ritardo, oggi, prossimi 7 giorni, piu' avanti, senza data, fatte di recente), da spuntare con un clic. Dati da WorkOrderOverviewService::taskBoard().
 */
final class TaskBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'task-board'] + $config;
    }
}
