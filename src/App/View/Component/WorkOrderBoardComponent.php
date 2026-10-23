<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Bacheca delle commesse per stato (da avviare, in corso, in attesa del cliente, consegnate), con avanzamento, prossima fase, scadenza e segnali (in ritardo, ferma). Dati da App\Work\WorkOrderOverviewService::board().
 */
final class WorkOrderBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'work-order-board'] + $config;
    }
}
