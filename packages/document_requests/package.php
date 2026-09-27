<?php

declare(strict_types=1);

/**
 * Traccia i documenti richiesti/attesi per una pratica, con uno stato di
 * completezza indipendente dal semplice "file caricato o no" (che vive
 * gia' nella tabella 'attachments' del framework - Fase 0). Qui non si
 * duplica il contenuto del file: attachment_id punta alla riga di
 * 'attachments' quando il file arriva, resta NULL finche' manca.
 *
 * document_type e' un codice libero (VARCHAR), non un ENUM: il catalogo
 * di tipi documento (documento identita', biglietto, ricevuta, delega...)
 * e' vocabolario del progetto, non del framework.
 *
 * case_id e' 'base' - un documento richiesto senza una pratica a cui
 * appartenere non ha senso, quindi la dipendenza da 'cases' e' sempre
 * attiva quando si installa questo pacchetto. 'attachments' non compare
 * in nessuna dipendenza: e' una tabella del framework, sempre presente,
 * non un pacchetto installabile.
 */
return [
    'name' => 'document_requests',
    'label' => 'Documenti richiesti',
    'category' => 'operativita',
    'description' => 'Documenti richiesti e loro stato di completezza per ogni pratica.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'document_requests' => [
            'table' => 'document_requests',
            'fields' => [
                'case_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Pratica',
                    'base' => true,
                    'references' => ['package' => 'cases', 'table' => 'cases', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/cases/cerca'],
                ],
                'document_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Tipo documento',
                    'base' => true,
                    'help' => "Codice libero del progetto (es. 'documento identita', 'biglietto', 'ricevuta', 'delega').",
                ],
                'completeness_status' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato completezza',
                    'base' => true,
                ],
                'attachment_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'File caricato',
                    'defaultRequired' => false,
                    'references' => ['table' => 'attachments', 'column' => 'id'],
                    'help' => "Id della riga in 'attachments' quando il file e' stato caricato - resta vuoto finche' manca (nessun selettore file ancora, va inserito a mano).",
                ],
                'requested_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Richiesto il',
                    'defaultRequired' => false,
                ],
                'reviewed_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Verificato il',
                    'defaultRequired' => false,
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => false,
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Documento',
                                'area' => 'main',
                                'fields' => ['document_type', 'completeness_status', 'notes'],
                            ],
                            [
                                'title' => 'File',
                                'area' => 'main',
                                'fields' => ['attachment_id'],
                            ],
                            [
                                'title' => 'Collegamento e date',
                                'area' => 'sidebar',
                                'fields' => ['case_id', 'requested_at', 'reviewed_at'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
