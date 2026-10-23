<?php

declare(strict_types=1);

/**
 * Temperatura del cliente: si capisce in tempo se un cliente si sta
 * raffreddando (non lo si sente da tanto, l'ultimo contatto e' andato
 * male, un servizio non da' risultati, non paga, non risponde) e si
 * rischia un rinnovo o una commessa.
 *
 * customer_touchpoints: il registro dei contatti (quando, come, chi, com'e'
 * andata, prossimo passo). Il resto dei segnali arriva da cio' che c'e'
 * gia': esiti dei servizi, rinnovi, pagamenti, commesse ferme sul cliente,
 * appuntamenti (App\ClientHealth\HealthScore).
 */
return [
    'name' => 'client_health',
    'label' => 'Temperatura dei clienti',
    'category' => 'crm',
    'description' => 'Registro dei contatti con i clienti e segnali di raffreddamento: chi rischia di non rinnovare.',
    'dependsOn' => ['customers'],
    'selectableDirectly' => true,
    'entities' => [
        'customer_touchpoints' => [
            'table' => 'customer_touchpoints',
            'fields' => [
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                ],
                'contact_date' => [
                    'sql' => 'DATE',
                    'label' => 'Quando',
                    'base' => true,
                    'format' => 'date',
                ],
                'channel' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Come',
                    'base' => true,
                    'options' => ['telefono', 'whatsapp', 'email', 'incontro', 'videochiamata'],
                ],
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Chi l\'ha sentito',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'mood' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => "Com'era",
                    'base' => true,
                    'options' => ['contento', 'neutro', 'freddo', 'scontento'],
                ],
                'summary' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Di cosa si e\' parlato',
                    'base' => true,
                ],
                'next_step' => [
                    'sql' => 'VARCHAR(255)',
                    'label' => 'Prossimo passo',
                    'defaultRequired' => true,
                ],
                'next_date' => [
                    'sql' => 'DATE',
                    'label' => 'Entro il',
                    'defaultRequired' => true,
                    'format' => 'date',
                    'help' => "Con una data si crea l'attivita' per chi l'ha sentito.",
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Contatto', 'area' => 'main', 'fields' => ['customer_id', 'summary', 'next_step', 'next_date']],
                            ['title' => 'Quando e come', 'area' => 'sidebar', 'fields' => ['contact_date', 'channel', 'mood', 'user_id']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
