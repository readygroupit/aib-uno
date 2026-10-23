<?php

declare(strict_types=1);

/**
 * Appuntamenti e uscite: shooting, riprese, incontri dal cliente o in
 * sede, call, sopralluoghi. Data/ora, luogo, tempo di viaggio, chi c'e'
 * (un responsabile piu' altri colleghi), videochiamata, checklist da
 * portare. Il cliente e' facoltativo (un appuntamento puo' essere interno).
 *
 * Collegamento a una commessa (o altro) come per le attivita':
 * entity_type + entity_id, senza dipendere da quel pacchetto.
 *
 * google_event_id/meet_url/google_synced_at li scrive la sincronizzazione
 * con Google Calendar (App\Calendar\CalendarSyncService), non l'operatore.
 */
return [
    'name' => 'appointments',
    'label' => 'Appuntamenti',
    'category' => 'operativita',
    'description' => 'Calendario di uscite, incontri e call, con partecipanti, viaggio, videochiamata e sincronizzazione con Google Calendar.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'appointments' => [
            'table' => 'appointments',
            'fields' => [
                'title' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Titolo',
                    'base' => true,
                ],
                'kind' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Tipo',
                    'defaultRequired' => true,
                    'options' => ['shooting', 'riprese', 'incontro dal cliente', 'incontro in sede', 'call', 'sopralluogo', 'evento', 'altro'],
                ],
                'start_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Inizio',
                    'base' => true,
                    'format' => 'datetime',
                ],
                'end_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Fine',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                    'help' => "Vuota: un'ora dopo l'inizio.",
                ],
                'location' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Dove',
                    'defaultRequired' => true,
                    'help' => "Indirizzo completo, cosi' si apre la mappa con un clic.",
                ],
                'travel_minutes' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Viaggio (minuti, andata)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => 'Il calendario tiene libero il tempo per arrivare e tornare.',
                ],
                'is_online' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Videochiamata',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => 'Con Google Calendar collegato, il link Meet si crea da solo.',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['da confermare', 'confermato', 'fatto', 'annullato'],
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Responsabile',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'attendee_user_ids' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Vengono anche',
                    'defaultRequired' => true,
                    'optionsSource' => 'staff',
                    'help' => 'Ricevono l\'invito sul loro Google Calendar.',
                ],
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'entity_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Collegato a (tipo)',
                    'base' => true,
                    'nullable' => true,
                ],
                'entity_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Collegato a (id)',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                ],
                'checklist' => [
                    'sql' => 'TEXT',
                    'label' => 'Da portare / da fare',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => "Un punto per riga: attrezzatura, shot list, liberatorie...",
                ],
                'reminder_minutes_before' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Promemoria (minuti prima)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => 'Con Google Calendar collegato, il promemoria arriva da Google.',
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],
                'meet_url' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Link videochiamata',
                    'defaultRequired' => true,
                    'showIf' => ['field' => 'is_online', 'in' => ['1']],
                    'help' => 'Vuoto: lo crea Google (Meet). Oppure incolla un link Zoom o Teams.',
                ],
                'google_event_id' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Evento Google',
                    'defaultRequired' => true,
                ],
                'google_synced_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Sincronizzato con Google',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'source' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Creato da',
                    'defaultRequired' => true,
                    'help' => "'google' se e' nato su Google Calendar.",
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Appuntamento',
                                'area' => 'main',
                                'fields' => ['title', 'kind', 'stage', 'start_at', 'end_at'],
                            ],
                            [
                                'title' => 'Dove',
                                'area' => 'main',
                                'fields' => ['location', 'travel_minutes', 'is_online', 'meet_url'],
                            ],
                            [
                                'title' => 'Da portare e note',
                                'area' => 'main',
                                'fields' => ['checklist', 'notes'],
                            ],
                            [
                                'title' => 'Chi',
                                'area' => 'sidebar',
                                'fields' => ['assigned_user_id', 'attendee_user_ids', 'customer_id'],
                            ],
                            [
                                'title' => 'Promemoria',
                                'area' => 'sidebar',
                                'fields' => ['reminder_minutes_before'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
