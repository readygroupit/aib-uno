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
                                'fields' => ['address', 'municipality_id', 'postal_code'],
                            ],
                            [
                                'title' => 'Contatto',
                                'area' => 'sidebar',
                                'fields' => ['email', 'phone', 'mobile_phone', 'website'],
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
                                'fields' => ['iban', 'payment_terms_days'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
