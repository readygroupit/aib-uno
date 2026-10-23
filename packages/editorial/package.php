<?php

declare(strict_types=1);

/**
 * Piano editoriale: i contenuti social di ogni cliente, giorno per giorno
 * e canale per canale, dall'idea alla pubblicazione.
 *
 * editorial_posts: un contenuto (post, carosello, reel, storia, video)
 * con data, canale, testo, cosa serve per produrlo e lo stato. Il cliente
 * approva dalla sua area (link senza password, vedi App\Portal); chi lo
 * prepara se lo trova nell'organizer, perche' a ogni contenuto assegnato
 * corrisponde un'attivita' due giorni lavorativi prima
 * (App\Editorial\EditorialService).
 *
 * Due mani per contenuto: chi scrive il testo (writer_user_id, di solito
 * il social media manager) e chi lo realizza (assigned_user_id, grafica o
 * video), ognuno con la sua attivita'. Due approvazioni, registrate a
 * parte con chi e quando: quella interna (permesso editorial.approve,
 * "revisione interna") e quella del cliente (approved_at/approved_via).
 * Materiale di partenza e file finito sono link (Drive, archivio): niente
 * colonne di link copiate a mano in un Excel.
 *
 * editorial_quotas: quanti contenuti a settimana prevede il contratto per
 * cliente e canale; servono a dire "a Moretti mancano 3 post nelle
 * prossime due settimane".
 *
 * editorial_strategies: la strategia social del cliente (obiettivi,
 * pubblico, tono, rubriche): il piano del mese parte da qui, anche quando
 * lo propone l'IA.
 *
 * La pubblicazione automatica (Meta, TikTok) e' un passo successivo: qui
 * "programmato" e "pubblicato" li segna chi pubblica.
 */
return [
    'name' => 'editorial',
    'label' => 'Piano editoriale',
    'category' => 'contenuti',
    'description' => 'Contenuti social per cliente e canale, quantita\' da contratto, approvazione del cliente, preparazione nell\'organizer.',
    'dependsOn' => ['customers', 'tasks'],
    'selectableDirectly' => true,
    'permissions' => [
        'view' => ['Visualizzare piano editoriale', "Vedere l'elenco e aprire il dettaglio."],
        'create' => ['Creare piano editoriale', 'Aggiungere nuovi elementi.'],
        'edit' => ['Modificare piano editoriale', 'Modificare elementi esistenti.'],
        'delete' => ['Eliminare piano editoriale', 'Eliminare elementi esistenti.'],
        'approve' => ['Approvare i contenuti', "Revisione interna: approvare i contenuti prima che li veda il cliente."],
    ],
    'entities' => [
        'editorial_posts' => [
            'table' => 'editorial_posts',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'channel' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Canale',
                    'base' => true,
                    'options' => ['Instagram', 'Facebook', 'TikTok', 'LinkedIn'],
                ],
                'format' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Formato',
                    'base' => true,
                    'options' => ['post', 'carosello', 'reel', 'storia', 'video'],
                ],
                'publish_date' => [
                    'sql' => 'DATE',
                    'label' => 'Uscita',
                    'base' => true,
                    'format' => 'date',
                ],
                'publish_time' => [
                    'sql' => 'VARCHAR(5)',
                    'label' => 'Ora',
                    'defaultRequired' => true,
                    'format' => 'time',
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Idea / tema',
                    'base' => true,
                    'help' => "Es. 'Gusto del mese: pistacchio', 'Dietro le quinte dello shooting'.",
                ],
                'caption' => [
                    'sql' => 'TEXT',
                    'label' => 'Testo',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'media_notes' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Cosa serve',
                    'defaultRequired' => true,
                    'help' => "Es. '3 foto dallo shooting', 'video 15s verticale'.",
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['idea', 'in lavorazione', 'revisione interna', 'da approvare', 'approvato', 'da modificare', 'programmato', 'pubblicato', 'scartato'],
                    'help' => "'Da approvare' e' il cliente; prima c'e' la revisione interna.",
                ],
                'writer_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Scrive il testo',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Lo realizza (grafica o video)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'source_link' => [
                    'sql' => 'VARCHAR(500)',
                    'label' => 'Materiale di partenza',
                    'defaultRequired' => true,
                    'help' => 'Link alla cartella o ai file da usare (Drive, archivio).',
                ],
                'output_link' => [
                    'sql' => 'VARCHAR(500)',
                    'label' => 'File finito',
                    'defaultRequired' => true,
                    'help' => 'Link alla grafica o al video pronto.',
                ],
                'parent_post_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Nasce dal contenuto',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'client_feedback' => [
                    'sql' => 'TEXT',
                    'label' => 'Commento del cliente',
                    'defaultRequired' => true,
                ],
                'review_note' => [
                    'sql' => 'TEXT',
                    'label' => 'Nota della revisione interna',
                    'defaultRequired' => true,
                ],
                'internal_approved_by' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Approvato internamente da',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'internal_approved_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Approvato internamente il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'approved_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Approvato dal cliente il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'approved_via' => [
                    'sql' => 'VARCHAR(60)',
                    'label' => 'Come ha approvato il cliente',
                    'defaultRequired' => true,
                ],
                'revision_count' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Giri di modifiche',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'published_url' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Link al contenuto pubblicato',
                    'defaultRequired' => true,
                ],
                'task_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => "Attivita' di realizzazione",
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'writer_task_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => "Attivita' del testo",
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Contenuto', 'area' => 'main', 'fields' => ['customer_id', 'title', 'caption', 'media_notes', 'source_link', 'output_link']],
                            ['title' => 'Dove e quando', 'area' => 'sidebar', 'fields' => ['channel', 'format', 'publish_date', 'publish_time']],
                            ['title' => 'Chi e stato', 'area' => 'sidebar', 'fields' => ['writer_user_id', 'assigned_user_id', 'stage', 'published_url']],
                        ],
                    ],
                ],
            ],
        ],
        'editorial_quotas' => [
            'table' => 'editorial_quotas',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'channel' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Canale',
                    'base' => true,
                    'options' => ['Instagram', 'Facebook', 'TikTok', 'LinkedIn'],
                ],
                'per_week' => [
                    'sql' => 'TINYINT UNSIGNED',
                    'label' => 'Contenuti a settimana',
                    'base' => true,
                    'format' => 'integer',
                ],
                'formats' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Formati',
                    'defaultRequired' => true,
                    'help' => "Es. '2 post e 1 reel'.",
                ],
                'default_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Di solito li prepara',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Quantita\' da contratto', 'area' => 'main', 'fields' => ['customer_id', 'channel', 'per_week', 'formats', 'default_user_id']],
                        ],
                    ],
                ],
            ],
        ],
        'editorial_strategies' => [
            'table' => 'editorial_strategies',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'objectives' => [
                    'sql' => 'TEXT',
                    'label' => 'Obiettivi',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => "Es. 'piu' prenotazioni nei giorni feriali', 'far conoscere la linea bio'.",
                ],
                'audience' => [
                    'sql' => 'TEXT',
                    'label' => 'A chi parliamo',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'tone' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Tono di voce',
                    'defaultRequired' => true,
                    'help' => "Es. 'familiare, ironico, mai tecnico'.",
                ],
                'pillars' => [
                    'sql' => 'TEXT',
                    'label' => 'Rubriche',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => "Una per riga, es. 'Piatto della settimana', 'Dietro le quinte', 'Recensioni'.",
                ],
                'dos_donts' => [
                    'sql' => 'TEXT',
                    'label' => 'Da fare e da evitare',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Strategia social', 'area' => 'main', 'fields' => ['customer_id', 'objectives', 'audience', 'pillars']],
                            ['title' => 'Come', 'area' => 'sidebar', 'fields' => ['tone', 'dos_donts', 'notes']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
