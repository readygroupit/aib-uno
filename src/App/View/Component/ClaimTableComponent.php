<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * "Clienti" ricostruita come vista sulla pratica in corso di ognuno
 * (non il semplice elenco anagrafico) - vedi ListCustomersTool, l'unico
 * chiamante, per come si costruiscono riga/dettaglio/timeline.
 * Bespoke: la combinazione tabella+pannello dettaglio+timeline non si
 * ottiene componendo DataTableComponent/StatBoxComponent gia' esistenti
 * senza forzarli, meglio un componente dedicato che i due riusa comunque
 * per le card statistiche (vedi 'stats' sotto, gia' dati StatBoxComponent).
 */
final class ClaimTableComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'claim-table',
            'title' => $config['title'] ?? '',
            'createHref' => $config['createHref'] ?? null,
            'createLabel' => $config['createLabel'] ?? null,
            'exportHref' => $config['exportHref'] ?? null,
            // gia' dati StatBoxComponent (vedi ListCustomersTool) - qui solo trasportati
            'stats' => $config['stats'] ?? [],
            // [{label, value, count, active}]
            'filterTabs' => $config['filterTabs'] ?? [],
            'searchable' => (bool) ($config['searchable'] ?? true),
            'emptyMessage' => $config['emptyMessage'] ?? 'Nessun risultato.',
            // vedi ListCustomersTool::buildRow() per la forma completa di ogni riga
            'rows' => $config['rows'] ?? [],
        ];
    }
}
