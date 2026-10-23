<?php

declare(strict_types=1);

/**
 * Archivio di foto e video ricercabile a parole.
 *
 * I file restano dove sono (Google Drive dell'agenzia, o una cartella del
 * server/NAS): qui c'e' l'indice. Ogni file nuovo viene guardato una volta
 * dall'IA (le immagini, e alcuni fotogrammi dei video) e se ne salva la
 * descrizione: cosa si vede, persone si'/no, luogo, occasione, colori,
 * testo e marchi visibili, e per i video i momenti principali con il
 * minuto. Le ricerche ("foto per una comunione senza persone") leggono
 * queste descrizioni, non riguardano i file.
 *
 * media_assets: un file. analysis e' il JSON completo dell'IA; alcune voci
 * sono copiate in colonne (persone, ambiente, occasione, orientamento) per
 * filtrare veloce. description e tags si possono correggere a mano.
 * thumb_path: anteprima locale (data/media-thumbs), per mostrare i
 * risultati senza scaricare ogni volta dal Drive.
 */
return [
    'name' => 'media_library',
    'label' => 'Archivio foto e video',
    'category' => 'contenuti',
    'description' => 'Foto e video dell\'agenzia (Google Drive o cartella) descritti dall\'IA e ricercabili a parole: "foto per una comunione senza persone".',
    'dependsOn' => ['customers'],
    'selectableDirectly' => true,
    'permissions' => [
        'view' => ['Cercare nell\'archivio', 'Cercare foto e video e aprirli.'],
        'edit' => ['Correggere l\'archivio', 'Correggere descrizioni e parole chiave, rifare l\'analisi, sincronizzare.'],
    ],
    'entities' => [
        'media_assets' => [
            'table' => 'media_assets',
            'fields' => [
                'source' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Origine',
                    'base' => true,
                    'options' => ['google_drive', 'cartella'],
                ],
                'external_id' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Identificativo nell\'origine',
                    'base' => true,
                ],
                'name' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Nome del file',
                    'base' => true,
                ],
                'kind' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'Tipo',
                    'base' => true,
                    'options' => ['immagine', 'video'],
                ],
                'mime_type' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Formato',
                    'defaultRequired' => true,
                ],
                'folder' => [
                    'sql' => 'VARCHAR(500)',
                    'label' => 'Cartella',
                    'defaultRequired' => true,
                ],
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'web_link' => [
                    'sql' => 'VARCHAR(500)',
                    'label' => 'Link per aprirlo',
                    'defaultRequired' => true,
                ],
                'size_bytes' => [
                    'sql' => 'BIGINT UNSIGNED',
                    'label' => 'Dimensione',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'width' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Larghezza',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'height' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Altezza',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'duration_seconds' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Durata (secondi)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'modified_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Modificato il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'analysis_status' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Analisi',
                    'base' => true,
                    'options' => ['da analizzare', 'analizzato', 'errore', 'escluso'],
                ],
                'analysis_error' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Errore dell\'analisi',
                    'defaultRequired' => true,
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Descrizione',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => 'La scrive l\'IA; correggila se serve: le ricerche partono da qui.',
                ],
                'tags' => [
                    'sql' => 'VARCHAR(500)',
                    'label' => 'Parole chiave',
                    'defaultRequired' => true,
                    'help' => 'Separate da virgola.',
                ],
                'people_count' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Persone visibili',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'setting' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Ambiente',
                    'defaultRequired' => true,
                    'options' => ['interno', 'esterno', 'misto'],
                ],
                'occasion' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Occasione',
                    'defaultRequired' => true,
                ],
                'orientation' => [
                    'sql' => 'VARCHAR(12)',
                    'label' => 'Orientamento',
                    'defaultRequired' => true,
                    'options' => ['orizzontale', 'verticale', 'quadrato'],
                ],
                'analysis' => [
                    'sql' => 'JSON',
                    'label' => 'Analisi completa',
                    'defaultRequired' => true,
                ],
                'thumb_path' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Anteprima',
                    'defaultRequired' => true,
                ],
                'analyzed_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Analizzato il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Cosa contiene', 'area' => 'main', 'fields' => ['description', 'tags', 'occasion']],
                            ['title' => 'Dove', 'area' => 'sidebar', 'fields' => ['customer_id', 'people_count', 'setting']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
