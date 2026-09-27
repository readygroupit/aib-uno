<?php

declare(strict_types=1);

/**
 * Fascicolo/pratica: il lavoro che si segue per un cliente, con uno
 * stato che cambia nel tempo. Non e' specifico del settore legale
 * nonostante l'esempio Assilevi - qualunque servizio con un "caso" da
 * seguire (supporto, sinistro, incarico professionale) puo' usarlo.
 *
 * primary_customer_id copre il caso comune (un cliente per pratica).
 * Quando una pratica riguarda piu' persone (es. "pratiche familiari
 * condivise" nel caso Assilevi) si usa il pacchetto separato
 * 'case_participants', installato solo dai progetti che ne hanno
 * davvero bisogno - non si forza una tabella ponte a chi ha sempre un
 * solo cliente per pratica.
 *
 * lead_id e' negoziabile: una pratica puo' nascere direttamente (senza
 * mai passare da un lead) o da una conversione - dipendenza da 'leads'
 * derivata dalla selezione, non forzata.
 */
return [
    'name' => 'cases',
    'label' => 'Pratiche',
    'category' => 'operativita',
    'description' => 'Fascicoli e pratiche da seguire nel tempo.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'cases' => [
            'table' => 'cases',
            'fields' => [
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Titolo pratica',
                    'defaultRequired' => false,
                ],
                'category' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Categoria',
                    'defaultRequired' => false,
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato pratica',
                    'base' => true,
                ],
                'priority' => [
                    'sql' => 'TINYINT UNSIGNED',
                    'label' => 'Priorita',
                    'defaultRequired' => false,
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Responsabile',
                    'defaultRequired' => false,
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/users/cerca'],
                ],
                'primary_customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'lead_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Contatto di origine',
                    'defaultRequired' => false,
                    'references' => ['package' => 'leads', 'table' => 'leads', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/leads/cerca'],
                ],
                'opened_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Data apertura',
                    'defaultRequired' => false,
                ],
                'closed_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Data chiusura',
                    'defaultRequired' => false,
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => false,
                ],
            ],
        ],
    ],
];
