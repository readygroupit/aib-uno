<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Testata di una commessa con le fasi da spuntare, i tempi e le azioni (attesa del cliente, sollecitato, pausa, consegnata). Dati da WorkOrderOverviewService::detail().
 */
final class WorkOrderSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'work-order-summary'] + $config;
    }
}
