<?php

declare(strict_types=1);

/**
 * Documenti: le cartelle e i file del Drive dell'agenzia, gestiti dal
 * gestionale. Il file vero sta sul Drive; qui le sue informazioni (nome,
 * cartella, cliente, progetto, descrizione, parole chiave) per vederlo,
 * cercarlo (anche l'IA) e collegarlo, senza chiedere ogni volta al Drive.
 *
 * Funziona in tutte e due le direzioni (App\Documents\DocumentService):
 * una cartella creata o un file caricato dal gestionale nasce sul Drive; un
 * file aggiunto, rinominato, spostato o messo nel cestino sul Drive arriva
 * qui (job sync_documents). Niente si cancella per sempre: eliminare dal
 * gestionale mette nel cestino del Drive (si recupera per 30 giorni), e qui
 * resta la traccia (trashed_at).
 */
return [
    'name' => 'documents',
    'label' => 'Documenti',
    'category' => 'contenuti',
    'description' => 'Cartelle e file sul Drive, gestiti dal gestionale: cartelle di clienti e progetti, caricamento, ricerca.',
    'dependsOn' => ['connectors'],
    'selectableDirectly' => true,
    'entities' => [
        'document_folders' => [
            'table' => 'document_folders',
            'fields' => [
                'external_id' => ['sql' => 'VARCHAR(120)', 'label' => 'Id sul Drive', 'base' => true],
                'parent_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Dentro a', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'name' => ['sql' => 'VARCHAR(255)', 'label' => 'Cartella', 'base' => true],
                'customer_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Cliente', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'work_order_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Progetto', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'web_link' => ['sql' => 'VARCHAR(500)', 'label' => 'Link', 'base' => true, 'nullable' => true],
                'drive_modified_at' => ['sql' => 'DATETIME', 'label' => 'Modificata sul Drive', 'base' => true, 'nullable' => true, 'format' => 'datetime'],
                'trashed_at' => ['sql' => 'DATETIME', 'label' => 'Nel cestino dal', 'base' => true, 'nullable' => true, 'format' => 'datetime'],
                'synced_at' => ['sql' => 'DATETIME', 'label' => 'Letta il', 'base' => true, 'nullable' => true, 'format' => 'datetime'],
            ],
            'layout' => ['sections' => [['label' => null, 'boxes' => [['title' => 'Cartella', 'area' => 'main', 'fields' => ['name']]]]]],
        ],
        'documents' => [
            'table' => 'documents',
            'fields' => [
                'external_id' => ['sql' => 'VARCHAR(120)', 'label' => 'Id sul Drive', 'base' => true],
                'folder_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Cartella', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'name' => ['sql' => 'VARCHAR(255)', 'label' => 'Nome', 'base' => true],
                'mime_type' => ['sql' => 'VARCHAR(150)', 'label' => 'Tipo', 'base' => true, 'nullable' => true],
                'size_bytes' => ['sql' => 'BIGINT UNSIGNED', 'label' => 'Dimensione', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'web_link' => ['sql' => 'VARCHAR(500)', 'label' => 'Link', 'base' => true, 'nullable' => true],
                'md5' => ['sql' => 'VARCHAR(64)', 'label' => 'Impronta', 'base' => true, 'nullable' => true],
                'drive_modified_at' => ['sql' => 'DATETIME', 'label' => 'Modificato sul Drive', 'base' => true, 'nullable' => true, 'format' => 'datetime'],
                'description' => ['sql' => 'TEXT', 'label' => 'Cosa contiene', 'base' => true, 'nullable' => true, 'input' => 'textarea', 'help' => 'Serve per ritrovarlo: la ricerca (e l\'IA) leggono questo, non il Drive.'],
                'tags' => ['sql' => 'VARCHAR(500)', 'label' => 'Parole chiave', 'base' => true, 'nullable' => true, 'help' => 'Separate da virgola, es. preventivo, firmato.'],
                'customer_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Cliente', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'work_order_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Progetto', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'uploaded_by' => ['sql' => 'INT UNSIGNED', 'label' => 'Caricato da', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'origin' => ['sql' => 'VARCHAR(10)', 'label' => 'Da dove', 'base' => true, 'options' => ['sito', 'drive']],
                'trashed_at' => ['sql' => 'DATETIME', 'label' => 'Nel cestino dal', 'base' => true, 'nullable' => true, 'format' => 'datetime'],
                'synced_at' => ['sql' => 'DATETIME', 'label' => 'Letto il', 'base' => true, 'nullable' => true, 'format' => 'datetime'],
                // Caricato da una fase del progetto; finche' il Drive non c'e', il file sta qui (local_path) e poi parte.
                'task_id' => ['sql' => 'INT UNSIGNED', 'label' => 'Attivita\'', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'local_path' => ['sql' => 'VARCHAR(500)', 'label' => 'In attesa del Drive', 'base' => true, 'nullable' => true],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'File', 'area' => 'main', 'fields' => ['name', 'description', 'tags']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
