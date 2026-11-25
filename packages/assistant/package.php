<?php

declare(strict_types=1);

/**
 * L'assistente che parla per primo: guarda progetti, preventivi, date
 * chiave e attivita' e dice a ciascuno cosa fare e decidere, senza che
 * glielo chieda (home, prompt "assistente", avvisi in app ed email).
 *
 * Le regole sono nel codice (App\Assistant\AssistantRules: cosa guardano,
 * dopo quanti giorni, a chi); qui solo cio' che la direzione cambia di
 * ciascuna: accesa o spenta, i giorni, e per quelle che possono fare
 * qualcosa verso il cliente l'autonomia (solo avviso, chiede conferma, in
 * automatico).
 *
 * Pacchetto di sistema: niente permessi propri, le regole le cambia chi
 * configura il gestionale (users.manage); ognuno vede solo le sue cose.
 *
 * assistant_actions: quello che l'assistente ha proposto o fatto (un
 * sollecito da mandare, un avviso gia' dato una volta), per non ripetersi
 * e per lasciare traccia.
 */
return [
    'name' => 'assistant',
    'label' => 'Assistente',
    'category' => 'sistema',
    'description' => "L'assistente che dice a ciascuno cosa fare e decidere, con l'autonomia scelta per ogni regola.",
    'dependsOn' => ['tasks', 'notifications'],
    'selectableDirectly' => true,
    'entities' => [
        'assistant_rules' => [
            'table' => 'assistant_rules',
            'fields' => [
                'code' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Regola',
                    'base' => true,
                ],
                'is_enabled' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Accesa',
                    'base' => true,
                    'format' => 'boolean',
                ],
                'days' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Dopo quanti giorni',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                ],
                'autonomy' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Cosa fa da solo',
                    'base' => true,
                    'nullable' => true,
                    'options' => ['solo avviso', 'chiede conferma', 'in automatico'],
                    'help' => "Solo avviso: ti ricorda e basta. Chiede conferma: prepara il messaggio, lo mandi tu. In automatico: lo manda e ti dice cosa ha fatto.",
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Regola', 'area' => 'main', 'fields' => ['code', 'is_enabled', 'days', 'autonomy']],
                        ],
                    ],
                ],
            ],
        ],

        'assistant_actions' => [
            'table' => 'assistant_actions',
            'fields' => [
                'rule_code' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Regola',
                    'base' => true,
                ],
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Per',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'entity_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Collegato a',
                    'base' => true,
                    'nullable' => true,
                ],
                'entity_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Id collegato',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                ],
                'dedupe_key' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Chiave',
                    'base' => true,
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Cosa',
                    'base' => true,
                ],
                'body' => [
                    'sql' => 'TEXT',
                    'label' => 'Perche\'',
                    'base' => true,
                    'nullable' => true,
                ],
                'email_to' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'A',
                    'base' => true,
                    'nullable' => true,
                    'help' => 'Piu\' indirizzi separati da virgola.',
                ],
                'email_subject' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Oggetto',
                    'base' => true,
                    'nullable' => true,
                ],
                'email_body' => [
                    'sql' => 'TEXT',
                    'label' => 'Testo',
                    'base' => true,
                    'nullable' => true,
                    'input' => 'textarea',
                ],
                'stage' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Stato',
                    'base' => true,
                    'options' => ['proposta', 'eseguita', 'scartata', 'segnalata'],
                ],
                'decided_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Deciso il',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'datetime',
                ],
                'decided_by' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Deciso da',
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Messaggio per il cliente', 'area' => 'main', 'fields' => ['email_to', 'email_subject', 'email_body']],
                        ],
                    ],
                ],
            ],
        ],

        // Il calendario dei periodi forti (Natale, San Valentino, vendemmia...):
        // in anticipo l'assistente propone i clienti per cui contano (parole
        // chiave nel profilo, nei prodotti, nel settore).
        'seasonal_events' => [
            'table' => 'seasonal_events',
            'fields' => [
                'name' => ['sql' => 'VARCHAR(80)', 'label' => 'Periodo', 'base' => true],
                'month' => ['sql' => 'SMALLINT UNSIGNED', 'label' => 'Mese', 'base' => true, 'nullable' => true, 'format' => 'integer', 'help' => '1-12. Vuoto se si muove con la Pasqua.'],
                'day' => ['sql' => 'SMALLINT UNSIGNED', 'label' => 'Giorno', 'base' => true, 'nullable' => true, 'format' => 'integer'],
                'easter_offset' => ['sql' => 'SMALLINT', 'label' => 'Giorni dalla Pasqua', 'base' => true, 'nullable' => true, 'format' => 'integer', 'help' => 'Solo per i periodi legati alla Pasqua (0 = Pasqua).'],
                'lead_days' => ['sql' => 'SMALLINT UNSIGNED', 'label' => 'Con quanto anticipo', 'base' => true, 'format' => 'integer', 'help' => 'Giorni prima: quando proporre al cliente di prepararsi.'],
                'keywords' => ['sql' => 'VARCHAR(500)', 'label' => 'Per chi conta', 'base' => true, 'help' => 'Parole separate da virgola: si cercano nel profilo, nei prodotti e nel settore del cliente (anche l\'inizio di una parola: "regal" vale per regalo e regali).'],
                'idea' => ['sql' => 'TEXT', 'label' => 'Cosa proporre', 'base' => true, 'nullable' => true, 'input' => 'textarea'],
                'is_enabled' => ['sql' => 'TINYINT(1)', 'label' => 'Acceso', 'base' => true, 'format' => 'boolean'],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Periodo', 'area' => 'main', 'fields' => ['name', 'keywords', 'idea']],
                            ['title' => 'Quando', 'area' => 'sidebar', 'fields' => ['month', 'day', 'easter_offset', 'lead_days', 'is_enabled']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
