<?php

declare(strict_types=1);

/**
 * Commesse: il lavoro da consegnare a un cliente (un sito, uno shooting,
 * una campagna...), con le sue fasi. Le fasi sono attivita' del pacchetto
 * 'tasks' collegate alla commessa (entity_type 'work_orders'): cosi' chi
 * pianifica la giornata trova in un posto solo le fasi delle commesse e le
 * attivita' sciolte.
 *
 * I tipi di commessa sono modelli: ogni tipo ha le sue fasi gia' pronte
 * (con il giorno previsto rispetto all'inizio, il tempo, se aspettano il
 * cliente, una checklist). Creando una commessa di quel tipo le fasi
 * diventano attivita' (App\Work\WorkOrderService).
 *
 * Lo stato "in attesa del cliente" lo mette il sistema quando tocca a una
 * fase che aspetta il cliente, e lo toglie quando quella fase e' fatta;
 * last_activity_at serve a vedere le commesse ferme.
 */
return [
    'name' => 'work_orders',
    'label' => 'Commesse',
    'category' => 'operativita',
    'description' => 'Commesse per i clienti, con fasi a modello, attese del cliente, scadenze e tempi.',
    'dependsOn' => ['customers', 'tasks'],
    'selectableDirectly' => true,
    'entities' => [
        'work_order_types' => [
            'table' => 'work_order_types',
            'fields' => [
                'name' => [
                    'sql' => 'VARCHAR(150)',
                    'label' => 'Nome',
                    'base' => true,
                    'help' => "Es. 'Sito vetrina', 'Shooting fotografico', 'Campagna ADV'.",
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Descrizione',
                    'defaultRequired' => true,
                ],
                'default_days' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Durata tipica (giorni)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Serve a proporre la data di consegna.",
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['in uso', 'non in uso'],
                ],
                'folder_template' => [
                    'sql' => 'TEXT',
                    'label' => 'Cartelle del progetto',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => "Una per riga (es. 01 Preventivo, 02 Brief, 03 Bozze): nascono sul Drive nella cartella di ogni progetto nuovo.",
                ],
                'service_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Servizio del listino',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'autocomplete' => ['source' => '/riferimenti/services/cerca'],
                    'help' => "Il servizio che segue questo processo: chi lo vende a un cliente trova gia' fasi, ruoli e date.",
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Tipo di commessa', 'area' => 'main', 'fields' => ['name', 'description', 'folder_template']],
                            ['title' => 'Uso', 'area' => 'sidebar', 'fields' => ['service_id', 'default_days', 'stage']],
                        ],
                    ],
                ],
            ],
        ],

        'work_order_type_steps' => [
            'table' => 'work_order_type_steps',
            'fields' => [
                'work_order_type_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Tipo di commessa',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_order_types', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/work_order_types/cerca'],
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'base' => true,
                    'format' => 'integer',
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Fase',
                    'base' => true,
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Cosa si fa',
                    'defaultRequired' => true,
                ],
                'offset_days' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Giorno previsto (dall\'inizio)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "0 = il giorno di inizio della commessa. Vuoto: la data la danno le date chiave.",
                ],
                'estimated_minutes' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Tempo previsto (minuti)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'waits_for_client' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Aspetta il cliente',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => "Testi, foto, un'approvazione: la commessa passa da sola 'in attesa del cliente'.",
                ],
                'checklist' => [
                    'sql' => 'TEXT',
                    'label' => 'Checklist',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => 'Un punto per riga.',
                ],
                'phase' => [
                    'sql' => 'VARCHAR(60)',
                    'label' => 'Fase del processo',
                    'defaultRequired' => true,
                    'help' => "Raggruppa le attivita': es. 'Preventivo', 'Avvio', 'Creativita''.",
                ],
                'kind' => [
                    'required' => true,
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Tipo',
                    'defaultRequired' => true,
                    'options' => ['lavoro', 'approvazione', 'attesa del cliente', 'data chiave', 'briefing', 'raccolta informazioni', 'squadra', 'appuntamento', 'consegna', 'approvazione del cliente'],
                    'help' => "Approvazione: chi ha il ruolo approva o rimanda indietro. Data chiave: si chiude fissando la data. Briefing: la checklist sono le domande, le risposte vanno nel profilo del cliente. Raccolta informazioni: la checklist sono le domande, le risposte restano nel progetto e le vede chi prepara il preventivo. Squadra: nella fase si scelgono le persone per ogni ruolo. Appuntamento: nella fase si fissa l'appuntamento. Consegna: nella fase si spuntano le cose da consegnare. Approvazione del cliente: si segnano la data e l'email con cui il cliente approva.",
                ],
                'role_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi la fa (ruolo)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Per un'approvazione: chi approva.",
                ],
                'milestone_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Data chiave',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'showIf' => ['field' => 'kind', 'in' => ['data chiave']],
                ],
                'completes_on' => [
                    'sql' => 'VARCHAR(40)',
                    'label' => 'Si chiude da sola quando',
                    'defaultRequired' => true,
                    'options' => ['preventivo preparato', 'preventivo approvato', 'preventivo inviato', 'preventivo accettato'],
                ],
                'reject_step_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Se non approvata, si riapre',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'showIf' => ['field' => 'kind', 'in' => ['approvazione']],
                ],
                'is_repeatable' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Si puo\' ripetere',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => 'Correzioni, modifiche del cliente: ogni giro e\' un\'attivita\' nuova.',
                ],
                'is_optional' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Facoltativa',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => 'La squadra decide se farla (es. la moodboard): si puo\' saltare.',
                ],
                'folder_name' => [
                    'sql' => 'VARCHAR(120)',
                    'label' => 'Cartella dei file',
                    'base' => true,
                    'nullable' => true,
                    'help' => "Dove vanno i file caricati dalla fase, dentro la cartella del progetto (es. 03 Bozze). Vuoto: nella cartella del progetto.",
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Fase', 'area' => 'main', 'fields' => ['work_order_type_id', 'phase', 'title', 'description', 'checklist']],
                            ['title' => 'Chi e come', 'area' => 'main', 'fields' => ['kind', 'role_id', 'milestone_id', 'reject_step_id', 'completes_on', 'is_repeatable', 'is_optional', 'folder_name']],
                            ['title' => 'Quando e quanto', 'area' => 'sidebar', 'fields' => ['position', 'offset_days', 'estimated_minutes', 'waits_for_client']],
                        ],
                    ],
                ],
            ],
        ],

        'work_orders' => [
            'table' => 'work_orders',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'work_order_type_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Tipo di commessa',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_order_types', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/work_order_types/cerca'],
                    'help' => "Le sue fasi diventano le attivita' della commessa. Vuoto: una commessa su misura, le fasi le aggiungi tu.",
                ],
                'customer_service_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Servizio venduto',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customer_services', 'table' => 'customer_services', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customer_services/cerca'],
                    'help' => 'Il servizio venduto da cui nasce, se c\'e\'.',
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Titolo',
                    'base' => true,
                    'help' => "Es. 'Nuovo sito della trattoria'.",
                ],
                'stage' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['contatto', 'preventivo', 'da avviare', 'in corso', 'in attesa del cliente', 'in pausa', 'consegnata', 'non acquisita', 'annullata'],
                ],
                'priority' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'Priorita',
                    'defaultRequired' => true,
                    'options' => ['alta', 'normale', 'bassa'],
                ],
                'owner_user_id' => [
                    'required' => true,
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Responsabile',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'supplier_customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Fornitore esterno',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'start_date' => [
                    'sql' => 'DATE',
                    'label' => 'Inizio',
                    'base' => true,
                    'format' => 'date',
                ],
                'due_date' => [
                    'sql' => 'DATE',
                    'label' => 'Consegna prevista',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'help' => 'Vuota: si calcola dalla durata tipica del tipo di commessa.',
                ],
                'delivered_at' => [
                    'sql' => 'DATE',
                    'label' => 'Consegnata il',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'stage', 'in' => ['consegnata']],
                ],
                'waiting_reason' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Cosa aspettiamo dal cliente',
                    'defaultRequired' => true,
                    'showIf' => ['field' => 'stage', 'in' => ['in attesa del cliente']],
                ],
                'waiting_since' => [
                    'sql' => 'DATE',
                    'label' => 'In attesa dal',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'stage', 'in' => ['in attesa del cliente']],
                ],
                'last_reminder_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Ultimo sollecito',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'last_activity_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Ultimo movimento',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],
                'quote_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Preventivo',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'autocomplete' => ['source' => '/riferimenti/quotes/cerca'],
                    'help' => "Il preventivo del progetto: bozza, approvazione, invio e firma fanno avanzare le fasi da soli.",
                ],
                'source_channel' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Come ci ha contattato',
                    'defaultRequired' => true,
                    'options' => ['sito', 'email', 'telefono', 'whatsapp', 'social', 'passaparola', 'di persona', 'altro'],
                ],
                'lost_reason' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Perche\' non e\' andata',
                    'defaultRequired' => true,
                    'showIf' => ['field' => 'stage', 'in' => ['non acquisita']],
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Commessa', 'area' => 'main', 'fields' => ['customer_id', 'work_order_type_id', 'title', 'customer_service_id', 'quote_id', 'source_channel']],
                            ['title' => 'Tempi', 'area' => 'main', 'fields' => ['start_date', 'due_date', 'delivered_at']],
                            ['title' => 'Attesa del cliente', 'area' => 'main', 'fields' => ['waiting_reason', 'waiting_since', 'lost_reason']],
                            ['title' => 'Chi la segue', 'area' => 'sidebar', 'fields' => ['owner_user_id', 'supplier_customer_id', 'stage', 'priority']],
                            ['title' => 'Note', 'area' => 'main', 'fields' => ['notes']],
                        ],
                    ],
                ],
            ],
        ],

        // Il processo di un servizio: i ruoli che ci lavorano. Le varianti
        // di squadra non servono come tabella: un esterno e' un utente
        // messo nel ruolo, "con aiuti" e' un ruolo facoltativo riempito.
        'work_order_type_roles' => [
            'table' => 'work_order_type_roles',
            'fields' => [
                'work_order_type_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Processo',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_order_types', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/work_order_types/cerca'],
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'base' => true,
                    'format' => 'integer',
                ],
                'label' => [
                    'sql' => 'VARCHAR(80)',
                    'label' => 'Ruolo',
                    'base' => true,
                    'help' => "Es. 'Commerciale', 'Coordinatore creativo'.",
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Cosa fa',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'default_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Di solito',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                    'help' => 'Proposto in ogni progetto nuovo; si cambia progetto per progetto.',
                ],
                'customer_team_role' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Dalla squadra del cliente',
                    'defaultRequired' => true,
                    'options' => ['referente', 'social', 'grafica', 'video', 'sviluppo', 'adv', 'strategia', 'altro'],
                    'help' => 'Se il cliente ha gia\' qualcuno in quel ruolo, si propone lui.',
                ],
                'is_multiple' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Piu\' persone',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                ],
                'is_required' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Serve sempre',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => "Spento: il ruolo puo' restare vuoto (es. gli aiuti).",
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Ruolo', 'area' => 'main', 'fields' => ['work_order_type_id', 'label', 'description']],
                            ['title' => 'Chi', 'area' => 'sidebar', 'fields' => ['position', 'default_user_id', 'customer_team_role', 'is_multiple', 'is_required']],
                        ],
                    ],
                ],
            ],
        ],

        // Le date chiave del processo (briefing, presentazioni...): si
        // fissano a mano, e fissarle puo' mandare un'email al cliente.
        'work_order_type_milestones' => [
            'table' => 'work_order_type_milestones',
            'fields' => [
                'work_order_type_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Processo',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_order_types', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/work_order_types/cerca'],
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'base' => true,
                    'format' => 'integer',
                ],
                'label' => [
                    'sql' => 'VARCHAR(80)',
                    'label' => 'Data chiave',
                    'base' => true,
                    'help' => "Es. 'Briefing con il cliente', 'Presentazione interna'.",
                ],
                'set_by_role_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'La fissa (ruolo)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'after_step_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Solo dopo la fase',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Es. la presentazione al cliente solo dopo l'approvazione interna.",
                ],
                'creates_appointment' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Crea l\'appuntamento',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => 'Con l\'invito in calendario alla squadra del progetto.',
                ],
                'client_email_subject' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Email al cliente: oggetto',
                    'defaultRequired' => true,
                    'help' => 'Vuoto: nessuna email. Si puo\' usare {cliente}, {data}, {progetto}, {squadra}, {firma}.',
                ],
                'client_email_body' => [
                    'sql' => 'TEXT',
                    'label' => 'Email al cliente: testo',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'help' => "Fissando la data si vede l'email gia' compilata e si puo' cambiare prima di inviarla.",
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Data chiave', 'area' => 'main', 'fields' => ['work_order_type_id', 'label', 'client_email_subject', 'client_email_body']],
                            ['title' => 'Chi e quando', 'area' => 'sidebar', 'fields' => ['position', 'set_by_role_id', 'after_step_id', 'creates_appointment']],
                        ],
                    ],
                ],
            ],
        ],

        // Cosa si consegna (logo, palette...): diventa la checklist del progetto.
        'work_order_type_deliverables' => [
            'table' => 'work_order_type_deliverables',
            'fields' => [
                'work_order_type_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Processo',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_order_types', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/work_order_types/cerca'],
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'base' => true,
                    'format' => 'integer',
                ],
                'label' => [
                    'sql' => 'VARCHAR(120)',
                    'label' => 'Cosa si consegna',
                    'base' => true,
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Cosa comprende',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'always_included' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Sempre compreso',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => 'Spento: si aggiunge al progetto solo se venduto.',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Cosa si consegna', 'area' => 'main', 'fields' => ['work_order_type_id', 'label', 'description']],
                            ['title' => 'Uso', 'area' => 'sidebar', 'fields' => ['position', 'always_included']],
                        ],
                    ],
                ],
            ],
        ],

        // La squadra del progetto: chi ci lavora e con quale ruolo.
        'work_order_members' => [
            'table' => 'work_order_members',
            'fields' => [
                'work_order_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Progetto',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_orders', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/work_orders/cerca'],
                ],
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'role_id' => [
                    'required' => true,
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Ruolo',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'notes' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Nella squadra', 'area' => 'main', 'fields' => ['work_order_id', 'user_id', 'role_id', 'notes']],
                        ],
                    ],
                ],
            ],
        ],

        // Le date chiave del progetto: fissarne una crea l'appuntamento,
        // manda (se c'e') l'email al cliente e chiude la sua fase.
        'work_order_milestones' => [
            'table' => 'work_order_milestones',
            'fields' => [
                'work_order_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Progetto',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_orders', 'column' => 'id'],
                ],
                'milestone_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Dal processo',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'base' => true,
                    'format' => 'integer',
                ],
                'label' => [
                    'sql' => 'VARCHAR(80)',
                    'label' => 'Data chiave',
                    'base' => true,
                ],
                'scheduled_at' => [
                    'required' => true,
                    'sql' => 'DATETIME',
                    'label' => 'Quando',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'send_client_email' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Manda l\'email al cliente',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => 'Parte al salvataggio, una volta sola. Rileggila qui sotto.',
                ],
                'client_email_to' => [
                    'required' => true,
                    'sql' => 'VARCHAR(255)',
                    'label' => 'A',
                    'defaultRequired' => true,
                    'showIf' => ['field' => 'send_client_email', 'in' => ['1']],
                    'help' => 'Piu\' indirizzi separati da virgola.',
                ],
                'client_email_subject' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Oggetto',
                    'defaultRequired' => true,
                    'showIf' => ['field' => 'send_client_email', 'in' => ['1']],
                ],
                'client_email_body' => [
                    'sql' => 'TEXT',
                    'label' => 'Testo',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                    'showIf' => ['field' => 'send_client_email', 'in' => ['1']],
                ],
                'client_email_sent_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Email inviata il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
                'appointment_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Appuntamento',
                    'defaultRequired' => true,
                    'format' => 'integer',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Data', 'area' => 'main', 'fields' => ['work_order_id', 'label', 'scheduled_at']],
                            ['title' => 'Email al cliente', 'area' => 'main', 'fields' => ['send_client_email', 'client_email_to', 'client_email_subject', 'client_email_body']],
                        ],
                    ],
                ],
            ],
        ],

        'work_order_deliverables' => [
            'table' => 'work_order_deliverables',
            'fields' => [
                'work_order_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Progetto',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_orders', 'column' => 'id'],
                ],
                'position' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Ordine',
                    'base' => true,
                    'format' => 'integer',
                ],
                'label' => [
                    'sql' => 'VARCHAR(120)',
                    'label' => 'Cosa si consegna',
                    'base' => true,
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Cosa comprende',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'is_done' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Pronto',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                ],
                'done_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Pronto il',
                    'defaultRequired' => true,
                    'format' => 'datetime',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Cosa si consegna', 'area' => 'main', 'fields' => ['work_order_id', 'label', 'description']],
                            ['title' => 'Stato', 'area' => 'sidebar', 'fields' => ['position', 'is_done']],
                        ],
                    ],
                ],
            ],
        ],

        // Le modifiche chieste dal cliente: ognuna compresa o extra.
        'work_order_change_requests' => [
            'table' => 'work_order_change_requests',
            'fields' => [
                'work_order_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Progetto',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'work_orders', 'column' => 'id'],
                ],
                'requested_at' => [
                    'sql' => 'DATE',
                    'label' => 'Chiesta il',
                    'base' => true,
                    'format' => 'date',
                ],
                'description' => [
                    'sql' => 'TEXT',
                    'label' => 'Cosa chiede il cliente',
                    'base' => true,
                    'input' => 'textarea',
                ],
                'assigned_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi la fa',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'is_extra' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Extra',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => 'Non compresa nel preventivo: da preventivare o fatturare a parte.',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['aperta', 'fatta', 'annullata'],
                ],
                'quote_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Preventivo dell\'extra',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'autocomplete' => ['source' => '/riferimenti/quotes/cerca'],
                    'showIf' => ['field' => 'is_extra', 'in' => ['1']],
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Modifica', 'area' => 'main', 'fields' => ['work_order_id', 'requested_at', 'description']],
                            ['title' => 'Chi e come', 'area' => 'sidebar', 'fields' => ['assigned_user_id', 'stage', 'is_extra', 'quote_id']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
