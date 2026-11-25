<?php

declare(strict_types=1);

/**
 * Quello che sappiamo di un cliente e che l'assistente usa per fare e
 * proporre le cose giuste:
 * - customer_profiles: un profilo per cliente (storia, mission, pubblico,
 *   concorrenti, stile, stagionalita'...). Tabella a parte dai clienti:
 *   testi lunghi letti solo quando servono (scheda, IA, briefing), non a
 *   ogni elenco - stesso motivo di communications/communication_contents.
 * - customer_notes: il diario del cliente (idee, decisioni, preferenze,
 *   risposte del briefing), anche di un progetto preciso. Un'idea o una
 *   decisione puo' stare nella roadmap interna, con il suo stato. Le
 *   persone menzionate vengono avvisate.
 */
return [
    'name' => 'client_knowledge',
    'label' => 'Conoscenza del cliente',
    'category' => 'crm',
    'description' => 'Profilo del cliente (storia, mission, stile...), diario di idee e decisioni, roadmap interna.',
    'dependsOn' => ['customers'],
    'selectableDirectly' => true,
    'entities' => [
        'customer_profiles' => [
            'table' => 'customer_profiles',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'history' => ['sql' => 'TEXT', 'label' => 'Storia e attivita\'', 'defaultRequired' => true, 'input' => 'textarea', 'help' => 'Chi sono, cosa fanno, da quanto.'],
                'products' => ['sql' => 'TEXT', 'label' => 'Prodotti e servizi', 'defaultRequired' => true, 'input' => 'textarea', 'help' => 'Cosa vendono, i piu\' importanti.'],
                'mission' => ['sql' => 'TEXT', 'label' => 'Mission e valori', 'defaultRequired' => true, 'input' => 'textarea'],
                'audience' => ['sql' => 'TEXT', 'label' => 'A chi si rivolgono', 'defaultRequired' => true, 'input' => 'textarea'],
                'competitors' => ['sql' => 'TEXT', 'label' => 'Concorrenti', 'defaultRequired' => true, 'input' => 'textarea'],
                'style_likes' => ['sql' => 'TEXT', 'label' => 'Stile: cosa piace', 'defaultRequired' => true, 'input' => 'textarea', 'help' => 'Marchi, colori, immagini che piacciono.'],
                'style_dislikes' => ['sql' => 'TEXT', 'label' => 'Stile: cosa evitare', 'defaultRequired' => true, 'input' => 'textarea', 'help' => 'Colori, stili, cose da non fare.'],
                'tone_of_voice' => ['sql' => 'TEXT', 'label' => 'Tono di voce', 'defaultRequired' => true, 'input' => 'textarea'],
                'brand_usage' => ['sql' => 'TEXT', 'label' => 'Dove si usa il marchio', 'defaultRequired' => true, 'input' => 'textarea', 'help' => 'Insegna, sito, social, stampa, packaging...'],
                'seasonality' => ['sql' => 'TEXT', 'label' => 'Stagionalita\' ed eventi', 'defaultRequired' => true, 'input' => 'textarea', 'help' => "Periodi forti, eventi, ricorrenze (es. Natale, estate, fiere): l'assistente propone le cose in tempo."],
                'notes' => ['sql' => 'TEXT', 'label' => 'Altro da sapere', 'defaultRequired' => true, 'input' => 'textarea'],
                'last_source' => ['sql' => 'VARCHAR(60)', 'label' => 'Ultimo aggiornamento da', 'defaultRequired' => true],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Chi sono', 'area' => 'main', 'fields' => ['customer_id', 'history', 'products', 'mission', 'audience', 'competitors']],
                            ['title' => 'Stile', 'area' => 'main', 'fields' => ['style_likes', 'style_dislikes', 'tone_of_voice', 'brand_usage']],
                            ['title' => 'Quando', 'area' => 'main', 'fields' => ['seasonality', 'notes']],
                        ],
                    ],
                ],
            ],
        ],

        'customer_notes' => [
            'table' => 'customer_notes',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'work_order_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Progetto',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'autocomplete' => ['source' => '/riferimenti/work_orders/cerca'],
                    'help' => 'Vuoto: riguarda il cliente in generale.',
                ],
                'kind' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Cos\'e\'',
                    'base' => true,
                    'options' => ['idea', 'decisione', 'preferenza', 'nota', 'briefing', 'preventivo'],
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'In breve',
                    'base' => true,
                    'help' => "Es. 'Il bollino sulle confezioni'.",
                ],
                'body' => [
                    'sql' => 'TEXT',
                    'label' => 'Dettagli',
                    'defaultRequired' => true,
                    'input' => 'textarea',
                ],
                'noted_at' => [
                    'sql' => 'DATE',
                    'label' => 'Quando',
                    'base' => true,
                    'format' => 'date',
                ],
                'source' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Da dove',
                    'defaultRequired' => true,
                    'options' => ['a mano', 'incontro', 'call', 'email', 'briefing', 'assistente'],
                ],
                'on_roadmap' => [
                    'sql' => 'TINYINT(1)',
                    'label' => 'Nella roadmap',
                    'defaultRequired' => true,
                    'format' => 'boolean',
                    'help' => 'La roadmap e\' interna: la vede la squadra, non il cliente.',
                ],
                'roadmap_status' => [
                    'sql' => 'VARCHAR(30)',
                    'label' => 'Stato nella roadmap',
                    'defaultRequired' => true,
                    'options' => ['da valutare', 'piaciuta al cliente', 'approvata', 'in corso', 'fatta', 'scartata'],
                    'showIf' => ['field' => 'on_roadmap', 'in' => ['1']],
                ],
                'due_date' => [
                    'sql' => 'DATE',
                    'label' => 'Entro',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'showIf' => ['field' => 'on_roadmap', 'in' => ['1']],
                ],
                'mentioned_user_ids' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Coinvolgi',
                    'defaultRequired' => true,
                    'optionsSource' => 'staff',
                    'help' => 'Ricevono un avviso, oltre alla squadra del cliente.',
                ],
                'author_user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Scritta da',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Cosa', 'area' => 'main', 'fields' => ['customer_id', 'work_order_id', 'kind', 'title', 'body']],
                            ['title' => 'Roadmap', 'area' => 'sidebar', 'fields' => ['on_roadmap', 'roadmap_status', 'due_date']],
                            ['title' => 'Chi e quando', 'area' => 'sidebar', 'fields' => ['noted_at', 'source', 'mentioned_user_ids']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
