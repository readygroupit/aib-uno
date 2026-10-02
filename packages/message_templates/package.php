<?php

declare(strict_types=1);

/**
 * Testi predefiniti per comunicazioni ricorrenti (solleciti documenti,
 * conferme di ricezione, aggiornamenti di stato). 'code' e' l'identificativo
 * stabile che una comunicazione registra in communications.template_code
 * (vedi packages/communications) - il nome puo' cambiare, il codice no.
 */
return [
    'name' => 'message_templates',
    'label' => 'Modelli di messaggio',
    'category' => 'crm',
    'description' => 'Testi predefiniti per email e WhatsApp ricorrenti.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'message_templates' => [
            'table' => 'message_templates',
            'fields' => [
                'code' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Codice',
                    'base' => true,
                    'help' => "Identificativo breve e stabile, senza spazi (es. 'sollecito_documenti'): resta registrato sulle comunicazioni inviate con questo modello.",
                ],
                'name' => [
                    'sql' => 'VARCHAR(150)',
                    'label' => 'Nome',
                    'base' => true,
                ],
                'channel' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Canale',
                    'base' => true,
                    'help' => "Vocabolario libero: valori tipici sono 'email' e 'whatsapp'.",
                ],
                'subject' => [
                    'sql' => 'VARCHAR(190)',
                    'label' => 'Oggetto',
                    'base' => true,
                    'nullable' => true,
                    'help' => "Solo per le email, WhatsApp non ha oggetto.",
                ],
                'body' => [
                    'sql' => 'TEXT',
                    'label' => 'Testo del messaggio',
                    'base' => true,
                    'input' => 'textarea',
                ],
            ],

            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            [
                                'title' => 'Modello',
                                'area' => 'main',
                                'fields' => ['name', 'code', 'channel', 'subject'],
                            ],
                            [
                                'title' => 'Testo',
                                'area' => 'main',
                                'fields' => ['body'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
