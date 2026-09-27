<?php

declare(strict_types=1);

/**
 * Collega piu' clienti a una stessa pratica (es. le "pratiche familiari
 * condivise" del caso Assilevi, ma generico: qualunque pratica con piu'
 * di uno stakeholder). Pacchetto separato da 'cases' apposta: chi ha
 * sempre un solo cliente per pratica (il caso comune, gia' coperto da
 * 'cases.primary_customer_id') non installa questa tabella ponte.
 *
 * case_id e customer_id sono entrambi 'base' (l'intero scopo del
 * pacchetto e' questa relazione, non ha senso installarlo senza uno dei
 * due) - la dipendenza da 'cases' e 'customers' e' quindi sempre
 * derivata, non serve dichiararla in 'dependsOn'.
 *
 * Non c'e' ancora un vincolo di unicita' su (case_id, customer_id) a
 * livello DB (evitare lo stesso cliente due volte sulla stessa pratica)
 * - se servisse si applicherebbe lo stesso pattern colonna generata
 * STORED + UNIQUE KEY gia' usato in Fase 0 per le tabelle ponte
 * (profile_permissions/user_permissions), non e' stato aggiunto qui per
 * restare al livello MVP finche' non e' un problema reale.
 */
return [
    'name' => 'case_participants',
    'label' => 'Partecipanti alla pratica',
    'category' => 'operativita',
    'description' => 'Collega piu\' clienti a una stessa pratica condivisa.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'case_participants' => [
            'table' => 'case_participants',
            'fields' => [
                'case_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Pratica',
                    'base' => true,
                    'references' => ['package' => 'cases', 'table' => 'cases', 'column' => 'id'],
                ],
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                ],
                'role' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Ruolo sulla pratica',
                    'defaultRequired' => false,
                ],
            ],
        ],
    ],
];
