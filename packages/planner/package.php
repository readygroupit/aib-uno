<?php

declare(strict_types=1);

/**
 * Organizer: la giornata di ognuno messa in ordine dal sistema
 * (App\Planner\DayPlanner), il carico del team, la settimana. Non ha dati
 * propri oltre agli orari di lavoro: legge attivita', fasi delle commesse
 * e appuntamenti.
 *
 * work_schedules: un orario per persona. Chi non ce l'ha lavora 9-18 con
 * pausa 13-14, dal lunedi' al venerdi'. focus_percent e' la parte della
 * giornata da riempire con il lavoro pianificato: il resto resta libero
 * per telefonate, imprevisti, clienti che passano (un socio commerciale
 * puo' avere 40%, un grafico 85%).
 *
 * Permessi propri (non i soliti quattro): vedere la propria giornata, e
 * vedere/organizzare quella di tutti.
 */
return [
    'name' => 'planner',
    'label' => 'Organizer',
    'category' => 'operativita',
    'description' => 'La giornata di ognuno in ordine, il carico del team, la settimana, con gli orari di lavoro.',
    'dependsOn' => ['tasks'],
    'selectableDirectly' => true,
    'permissions' => [
        'view' => ['Vedere la propria giornata', "L'organizer con le proprie attivita' e i propri appuntamenti."],
        'team' => ['Organizzare il lavoro del team', "Vedere la giornata e il carico di tutti, spostare e riassegnare il lavoro, impostare gli orari."],
    ],
    'entities' => [
        'work_schedules' => [
            'table' => 'work_schedules',
            'fields' => [
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Persona',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'start_time' => [
                    'sql' => 'VARCHAR(5)',
                    'format' => 'time',
                    'label' => 'Inizio',
                    'base' => true,
                ],
                'end_time' => [
                    'sql' => 'VARCHAR(5)',
                    'format' => 'time',
                    'label' => 'Fine',
                    'base' => true,
                ],
                'break_start' => [
                    'sql' => 'VARCHAR(5)',
                    'format' => 'time',
                    'label' => 'Pausa dalle',
                    'defaultRequired' => true,
                ],
                'break_end' => [
                    'sql' => 'VARCHAR(5)',
                    'format' => 'time',
                    'label' => 'Pausa alle',
                    'defaultRequired' => true,
                ],
                'workdays' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Giorni di lavoro',
                    'defaultRequired' => true,
                    'optionsSource' => 'weekdays',
                ],
                'focus_percent' => [
                    'sql' => 'TINYINT UNSIGNED',
                    'label' => 'Giornata da pianificare (%)',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'help' => "Quanta parte dell'orario riempire con attivita': il resto resta libero per telefonate e imprevisti.",
                ],
            ],
            'layout' => [
                'sections' => [
                    [
                        'label' => null,
                        'boxes' => [
                            ['title' => 'Orario', 'area' => 'main', 'fields' => ['user_id', 'start_time', 'end_time', 'break_start', 'break_end', 'workdays']],
                            ['title' => 'Pianificazione', 'area' => 'sidebar', 'fields' => ['focus_percent']],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
