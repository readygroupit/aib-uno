<?php

declare(strict_types=1);

namespace App\View\Component;

use App\Service\AuthService;

/**
 * Tabella paginata con azioni per riga filtrate in base ai permessi
 * dell'utente corrente. Il filtro sui permessi resta lato server (mai
 * fidarsi del client per questo). Config attesa: vedi DataTableComponent
 * precedente — stessa forma, ora restituita come dati invece che HTML.
 */
final class DataTableComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        /** @var AuthService $auth */
        $auth = $this->container->get(AuthService::class);

        $actions = array_values(array_filter(
            $config['actions'] ?? [],
            static fn (array $action) => empty($action['permission']) || $auth->hasPermission($action['permission'])
        ));

        $perPage = (int) ($config['perPage'] ?? count($config['rows'] ?? []));
        $total = (int) ($config['total'] ?? count($config['rows'] ?? []));
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'type' => 'data-table',
            // Tipo di entita' mostrata (es. 'user') - non un dato visivo,
            // serve al client per dire a Claude "l'operatore ha davanti
            // questo" quando manda la richiesta successiva (vedi hero.js
            // e ClaudeService::converse()): cosi' un riferimento ambiguo
            // come un nome proprio si puo' risolvere sul contesto invece
            // di restare indovinato o chiedere sempre chiarimento. Null
            // per le tabelle che non rappresentano un'entita' precisa.
            'entity' => $config['entity'] ?? null,
            // URL della pagina vera a cui corrisponde questo risultato
            // (es. '/utenti') - diverso da 'page' qui sotto, che e' il
            // numero di pagina della paginazione! Serve al client per
            // aggiornare la barra degli indirizzi (history.pushState)
            // quando il risultato arriva da un invio del prompt invece
            // che da una navigazione vera: null per i risultati che non
            // corrispondono a nessuna pagina reale (es. il catalogo
            // package, che non ha ancora una sua route).
            'url' => $config['url'] ?? null,
            'title' => $config['title'] ?? '',
            // Link "Nuovo ..." vicino al titolo, opzionale - null se la
            // lista non ha (ancora) una creazione, come il catalogo
            // package. createPermission segue la stessa logica di
            // filtro di 'actions' qui sotto.
            'createHref' => (empty($config['createPermission']) || $auth->hasPermission($config['createPermission']))
                ? ($config['createHref'] ?? null)
                : null,
            'createLabel' => $config['createLabel'] ?? 'Nuovo',
            'columns' => $config['columns'] ?? [],
            'rows' => $config['rows'] ?? [],
            'actions' => $actions,
            // Opzionali, usati finora solo da ListPermissionsTool: tab di
            // filtro (stesso linguaggio visivo del menu, vedi menu-grid.js)
            // + ricerca libera, tutto filtrato lato client - per tabelle
            // che non hanno bisogno di paginazione server (poche decine
            // di righe, non migliaia). Le altre liste non li passano e
            // restano esattamente come prima (solo tabella + paginazione).
            'filterField' => $config['filterField'] ?? null,
            'filterTabs' => $config['filterTabs'] ?? null,
            'searchable' => (bool) ($config['searchable'] ?? false),
            'page' => (int) ($config['page'] ?? 1),
            'totalPages' => max(1, $totalPages),
            'total' => $total,
            'emptyMessage' => $config['emptyMessage'] ?? 'Nessun risultato.',
        ];
    }
}
