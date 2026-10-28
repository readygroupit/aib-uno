<?php

declare(strict_types=1);

/**
 * Manifest di un pacchetto: catalogo di campi possibili, non uno schema
 * fisso. Quale sottoinsieme diventa colonna reale (e quale e' NOT NULL)
 * si decide per ogni progetto al momento dell'installazione - vedi
 * App\Package\PackageInstaller.
 *
 * 'base' => true indica un campo che ogni installazione avra' sempre
 * (es. email): non e' negoziabile, va sempre creato come colonna.
 * Tutti gli altri campi sono negoziabili, inclusi quelli che qui hanno
 * 'defaultRequired' => true: e' solo il suggerimento mostrato quando si
 * chiede all'operatore quali campi attivare per quel cliente (es. un
 * progetto B2B-only come Istrumed puo' scegliere di non includere
 * affatto first_name/last_name).
 *
 * municipality_id referenzia geo.municipalities tramite 'references' =>
 * [..., 'package' => 'geo']: 'geo' viene installato in automatico SOLO
 * se questo campo viene davvero scelto per il progetto (dipendenza
 * derivata dalla selezione, non dichiarata staticamente - vedi
 * App\Package\PackageInstallerRunner::resolveOrder()). Nessun
 * 'dependsOn' fisso qui: 'customers' funziona benissimo senza 'geo'.
 *
 * vat_number/sdi_code/pec/iban/payment_terms_days: dati anagrafici e di
 * fatturazione standard per un cliente B2B italiano - stesso principio
 * di 'leads': catalogo ricco installato per intero, non il minimo
 * indispensabile (vedi 'format' per come si collega a
 * App\Validation\FieldValidator).
 */
return [
    'name' => 'customers',
    'label' => 'Clienti',
    'category' => 'crm',
    'description' => 'Anagrafica clienti con dati fiscali e di contatto.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'customers' => [
            'table' => 'customers',
            'fields' => [
                // --- identita' ---
                'first_name' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Nome',
                    'defaultRequired' => true,
                ],
                'last_name' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Cognome',
                    'defaultRequired' => true,
                ],
                'company_name' => [
                    'sql' => 'VARCHAR(150)',
                    'label' => 'Ragione sociale',
                    'defaultRequired' => true,
                ],

                // --- contatto ---
                'email' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Email',
                    'defaultRequired' => true,
                    'base' => true,
                    'format' => 'email',
                ],
                'phone' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Telefono',
                    'defaultRequired' => true,
                    'format' => 'phone',
                ],
                'mobile_phone' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Cellulare',
                    'defaultRequired' => true,
                    'format' => 'phone',
                ],
                'website' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Sito web',
                    'defaultRequired' => true,
                ],

                // --- indirizzo ---
                'address' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Indirizzo',
                    'defaultRequired' => true,
                    'width' => 'full',
                ],
                'municipality_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Comune',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'geo', 'table' => 'municipalities', 'column' => 'id'],
                    // Digita il nome, arrivano i suggerimenti, si sceglie
                    // dalla lista - vedi Index\Controller\GeoController e
                    // public/js/components/form.js (buildAutocompleteField).
                    // 'format' resta 'integer': il valore VERO inviato dal
                    // form e' comunque l'id, l'autocomplete e' solo il
                    // modo in cui l'operatore lo sceglie.
                    'autocomplete' => ['source' => '/geo/comuni/cerca'],
                ],
                'postal_code' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'CAP',
                    'defaultRequired' => true,
                    // Si scrive il CAP, i suggerimenti compilano il comune
                    // (e scegliendo il comune si compila il CAP, se e' uno).
                    'autocomplete' => ['source' => '/geo/cap/cerca', 'freeText' => true],
                    'width' => 'quarter',
                ],

                // --- dati fiscali ---
                'vat_number' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Partita IVA',
                    'defaultRequired' => true,
                    'format' => 'partita_iva',
                ],
                'sdi_code' => [
                    'sql' => 'VARCHAR(10)',
                    'label' => 'Codice SDI',
                    'defaultRequired' => true,
                    'help' => "Codice destinatario per la fatturazione elettronica - 7 caratteri, oppure '0000000' se il cliente riceve solo via PEC.",
                ],
                'pec' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'PEC',
                    'defaultRequired' => true,
                    'format' => 'email',
                    'help' => 'Posta elettronica certificata - canale alternativo allo SDI per ricevere le fatture elettroniche.',
                ],

                // --- pagamento ---
                'iban' => [
                    'sql' => 'VARCHAR(34)',
                    'label' => 'IBAN',
                    'defaultRequired' => true,
                    'format' => 'iban',
                    'help' => 'Per addebiti diretti (RID/SDD) - facoltativo, serve solo se il cliente paga con questo metodo.',
                ],
                'payment_terms_days' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Termini di pagamento (giorni)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Giorni dalla data fattura entro cui e' dovuto il pagamento (es. 30 = fine mese successivo, pratica comune 'a 30 giorni').",
                ],

                'notes' => [
                    'sql' => 'TEXT',
                    'label' => 'Note',
                    'defaultRequired' => true,
                ],

                // --- rapporto ---
                'relationship' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Tipo di rapporto',
                    'defaultRequired' => true,
                    'options' => ['cliente', 'intermediario', 'fornitore', 'collaboratore', 'influencer'],
                    'help' => "Un intermediario porta lavori in cambio di una percentuale o di uno scambio; un fornitore o collaboratore lavora per voi; un influencer collabora alle campagne dei clienti.",
                ],
                'importance' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Importanza',
                    'defaultRequired' => true,
                    'options' => ['chiave', 'abituale', 'occasionale'],
                    'help' => 'Chiave: rapporto lungo e con tanti servizi, quasi un partner.',
                ],
                'sector' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Settore',
                    'defaultRequired' => true,
                ],
                'customer_since' => [
                    'sql' => 'DATE',
                    'label' => 'Cliente dal',
                    'defaultRequired' => true,
                    'format' => 'date',
                ],
                'account_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Referente interno',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                    'help' => 'Chi segue il cliente in azienda.',
                ],
                'referrer_customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Portato da',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                    'help' => "L'intermediario o il partner che ha portato il cliente.",
                ],
                'referral_percent' => [
                    'sql' => 'DECIMAL(5,2)',
                    'label' => 'Percentuale all\'intermediario (%)',
                    'defaultRequired' => true,
                    'format' => 'decimal',
                    'formatOptions' => ['precision' => 2],
                    'showIf' => ['field' => 'relationship', 'in' => ['intermediario']],
                ],
                // --- area cliente e pagamenti (scritti dal sistema, salvo le preferenze) ---
                'preferred_payment_method' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Metodo di pagamento preferito',
                    'defaultRequired' => true,
                    'options' => ['bonifico', 'carta', 'addebito SEPA', 'PayPal'],
                    'help' => "Lo puo' scegliere anche il cliente dalla sua area.",
                ],
                'auto_pay' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Pagamento automatico',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => "Rate e rinnovi addebitati da soli sulla carta o sul conto salvati (serve Stripe collegato e il consenso del cliente).",
                ],
                'portal_token' => [
                    'sql' => 'VARCHAR(64)',
                    'label' => 'Link area cliente',
                    'defaultRequired' => true,
                ],
                'stripe_customer_id' => [
                    'sql' => 'VARCHAR(64)',
                    'label' => 'Cliente su Stripe',
                    'defaultRequired' => true,
                ],
                'payment_mandate' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Metodo salvato per gli addebiti',
                    'defaultRequired' => true,
                ],
                'referral_terms' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Accordo con l\'intermediario',
                    'defaultRequired' => true,
                    'showIf' => ['field' => 'relationship', 'in' => ['intermediario']],
                    'help' => "Es. '10% sul primo anno' oppure 'scambio: grafica in cambio di contatti'.",
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => 'Anagrafica',
                        'boxes' => [
                            [
                                'title' => 'Identita',
                                'area' => 'main',
                                'fields' => ['first_name', 'last_name', 'company_name'],
                            ],
                            [
                                'title' => 'Indirizzo',
                                'area' => 'main',
                                'fields' => ['address', 'postal_code', 'municipality_id'],
                            ],
                            [
                                'title' => 'Contatto',
                                'area' => 'sidebar',
                                'fields' => ['email', 'phone', 'mobile_phone', 'website'],
                            ],
                        ],
                    ],
                    [
                        'label' => 'Rapporto',
                        'boxes' => [
                            [
                                'title' => 'Rapporto',
                                'area' => 'main',
                                'fields' => ['relationship', 'importance', 'sector', 'customer_since'],
                            ],
                            [
                                'title' => 'Chi lo segue',
                                'area' => 'sidebar',
                                'fields' => ['account_user_id', 'referrer_customer_id'],
                            ],
                            [
                                'title' => 'Accordo da intermediario',
                                'area' => 'main',
                                'fields' => ['referral_percent', 'referral_terms'],
                            ],
                        ],
                    ],
                    [
                        'label' => 'Fatturazione',
                        'boxes' => [
                            [
                                'title' => 'Dati fiscali',
                                'area' => 'main',
                                'fields' => ['vat_number', 'sdi_code', 'pec'],
                            ],
                            [
                                'title' => 'Note',
                                'area' => 'main',
                                'fields' => ['notes'],
                            ],
                            [
                                'title' => 'Pagamento',
                                'area' => 'sidebar',
                                'fields' => ['preferred_payment_method', 'auto_pay', 'iban', 'payment_terms_days'],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        // Le persone dell'azienda cliente (titolare, marketing, amministrazione):
        // un cliente ne ha piu' d'una, non sono campi del cliente.
        'customer_contacts' => [
            'table' => 'customer_contacts',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'full_name' => [
                    'sql' => 'VARCHAR(150)',
                    'label' => 'Nome e cognome',
                    'base' => true,
                ],
                'role' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Ruolo',
                    'defaultRequired' => true,
                    'help' => "Es. titolare, marketing, amministrazione.",
                ],
                'email' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Email',
                    'defaultRequired' => true,
                    'format' => 'email',
                ],
                'phone' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Telefono',
                    'defaultRequired' => true,
                    'format' => 'phone',
                ],
                'is_decision_maker' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Decide lui',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => "La persona che approva preventivi e rinnovi.",
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
                            [
                                'title' => 'Referente',
                                'area' => 'main',
                                'fields' => ['customer_id', 'full_name', 'role', 'notes'],
                            ],
                            [
                                'title' => 'Contatto',
                                'area' => 'sidebar',
                                'fields' => ['email', 'phone', 'is_decision_maker'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        // La squadra interna che segue il cliente: chi e' il referente, chi fa
        // i social, la grafica, i video... Serve a proporre a chi assegnare
        // contenuti e richieste e al filtro "i miei clienti".
        'customer_team_members' => [
            'table' => 'customer_team_members',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Persona',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'role' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Cosa fa per il cliente',
                    'base' => true,
                    'options' => ['referente', 'social', 'grafica', 'video', 'sviluppo', 'adv', 'strategia', 'altro'],
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
                            ['title' => 'Nella squadra del cliente', 'area' => 'main', 'fields' => ['customer_id', 'user_id', 'role', 'notes']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
