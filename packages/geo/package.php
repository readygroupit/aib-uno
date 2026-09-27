<?php

declare(strict_types=1);

/**
 * Pacchetto di supporto: dati geografici italiani (regione -> provincia ->
 * comune -> CAP), struttura e dati di riferimento presi dalla libreria
 * Core esistente (tabelle region/district/municipality/postal_code, gia'
 * usate da tutti i progetti client attuali). Non e' un catalogo di campi
 * negoziabili come 'customers' - ogni campo qui e' 'base': non ha senso
 * chiedere all'operatore "vuoi il nome della regione?", sono le tabelle
 * stesse ad essere l'unita' di scelta ('geo' si installa o non si
 * installa). Le tabelle restano separate non per opzionalita' ma perche'
 * sono entita' normalizzate a se stanti (regione/provincia/comune/CAP
 * esistono indipendentemente l'una dall'altra) - lo stesso motivo per cui
 * non sono mai state fuse in un'unica tabella nella libreria Core.
 *
 * 'selectableDirectly' => false: e' pensato per essere tirato dentro come
 * dipendenza di un pacchetto che ne ha bisogno (es. 'customers' con un
 * indirizzo), non scelto direttamente dall'operatore.
 *
 * L'ordine delle entity qui sotto e' anche l'ordine di creazione delle
 * tabelle (le FK richiedono che regions/districts esistano prima di
 * municipalities/postal_codes).
 */
return [
    'name' => 'geo',
    'label' => 'Dati geografici',
    'category' => 'sistema',
    'description' => 'Dati geografici italiani (regione, provincia, comune, CAP).',
    'dependsOn' => [],
    'selectableDirectly' => false,
    'entities' => [
        'regions' => [
            'table' => 'regions',
            'fields' => [
                'name' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Nome regione',
                    'base' => true,
                ],
            ],
        ],
        'districts' => [
            'table' => 'districts',
            'fields' => [
                'region_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Regione',
                    'base' => true,
                    'references' => ['table' => 'regions', 'column' => 'id'],
                ],
                'code' => [
                    'sql' => 'VARCHAR(2)',
                    'label' => 'Sigla provincia',
                    'base' => true,
                ],
                'name' => [
                    'sql' => 'VARCHAR(70)',
                    'label' => 'Nome provincia',
                    'base' => true,
                ],
            ],
        ],
        'municipalities' => [
            'table' => 'municipalities',
            'fields' => [
                'region_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Regione',
                    'base' => true,
                    'references' => ['table' => 'regions', 'column' => 'id'],
                ],
                'district_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Provincia',
                    'base' => true,
                    'references' => ['table' => 'districts', 'column' => 'id'],
                ],
                'cadastral_code' => [
                    'sql' => 'VARCHAR(5)',
                    'label' => 'Codice catastale',
                    'base' => true,
                ],
                'name' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Nome comune',
                    'base' => true,
                ],
            ],
        ],
        'postal_codes' => [
            'table' => 'postal_codes',
            'fields' => [
                'region_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Regione',
                    'base' => true,
                    'nullable' => true,
                    'references' => ['table' => 'regions', 'column' => 'id'],
                ],
                'district_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Provincia',
                    'base' => true,
                    'nullable' => true,
                    'references' => ['table' => 'districts', 'column' => 'id'],
                ],
                'municipality_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Comune',
                    'base' => true,
                    'nullable' => true,
                    'references' => ['table' => 'municipalities', 'column' => 'id'],
                ],
                'code' => [
                    'sql' => 'VARCHAR(5)',
                    'label' => 'CAP',
                    'base' => true,
                ],
                'municipality_code' => [
                    'sql' => 'VARCHAR(4)',
                    'label' => 'Codice Belfiore',
                    'base' => true,
                ],
                'district_code' => [
                    'sql' => 'VARCHAR(2)',
                    'label' => 'Sigla provincia',
                    'base' => true,
                ],
            ],
        ],
    ],
];
