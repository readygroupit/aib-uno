<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Lista clienti con pannello laterale (stesso impianto di 'claim-table'):
 * per ogni cliente cosa ha attivo, quanto vale, cosa scade, se e' in regola
 * con i pagamenti e la cosa da fare adesso. Righe costruite da
 * App\Billing\AccountOverviewService::customerRows().
 */
final class CustomerListComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'customer-list',
            'title' => $config['title'] ?? 'Clienti',
            'url' => $config['url'] ?? null,
            'createHref' => $config['createHref'] ?? null,
            'createLabel' => $config['createLabel'] ?? null,
            'stats' => $config['stats'] ?? [],
            'filterTabs' => $config['filterTabs'] ?? [],
            'rows' => $config['rows'] ?? [],
            'showBilling' => (bool) ($config['showBilling'] ?? true),
            'emptyMessage' => $config['emptyMessage'] ?? 'Nessun cliente.',
        ];
    }
}
