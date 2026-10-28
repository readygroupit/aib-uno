<?php

declare(strict_types=1);

/**
 * Registro attivita': cosa ha fatto ognuno e quanto ci ha messo.
 *
 * Due o tre volte al giorno il sistema chiede "cosa hai fatto?" e propone
 * le attivita' del piano (App\Worklog\CheckinService): si conferma con un
 * tocco e si corregge la durata. Le voci (work_entries) sommano il tempo
 * impiegato sull'attivita' (tasks.actual_minutes); da li' escono i tempi
 * medi per tipo di lavoro (App\Worklog\DurationStats), che l'organizer usa
 * per stimare meglio le giornate e che i titolari vedono con gli scarti.
 *
 * work_checkins tiene solo quando ognuno ha risposto, per non richiedere
 * due volte la stessa cosa.
 *
 * Attenzione: misura il lavoro delle persone. Prima di usarlo con dei
 * dipendenti va data l'informativa privacy e verificato l'uso con il
 * consulente del lavoro (art. 4 dello Statuto dei Lavoratori).
 */
return [
    'name' => 'worklog',
    'label' => 'Registro attivita',
    'category' => 'operativita',
    'description' => "Cosa ha fatto ognuno e quanto ci ha messo, tempi medi per tipo di lavoro, scarti da guardare.",
    'dependsOn' => ['tasks'],
    'selectableDirectly' => true,
    'permissions' => [
        'view' => ['Segnare il proprio lavoro', "Rispondere a \"cosa hai fatto?\" e vedere il proprio registro."],
        'team' => ['Vedere tempi e andamento del team', 'Tempi medi per tipo di lavoro, scarti, chi non ha segnato, carichi.'],
    ],
    'entities' => [
        'work_entries' => [
            'table' => 'work_entries',
            'fields' => [
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Persona',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/staff/cerca'],
                ],
                'work_date' => [
                    'sql' => 'DATE',
                    'label' => 'Giorno',
                    'base' => true,
                    'format' => 'date',
                ],
                'minutes' => [
                    'sql' => 'SMALLINT UNSIGNED',
                    'label' => 'Minuti',
                    'base' => true,
                    'format' => 'integer',
                    'help' => "Quanto ci hai messo: 90 = un'ora e mezza.",
                ],
                'title' => [
                    'sql' => 'VARCHAR(200)',
                    'label' => 'Cosa',
                    'base' => true,
                    'help' => "In breve, come lo diresti a un collega (es. 'Grafiche post Instagram Rossi').",
                ],
                'task_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => "Attivita'",
                    'base' => true,
                    'nullable' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'tasks', 'table' => 'tasks', 'column' => 'id'],
                ],
                'customer_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Cliente',
                    'defaultRequired' => true,
                    'format' => 'integer',
                    'references' => ['package' => 'customers', 'table' => 'customers', 'column' => 'id'],
                    'autocomplete' => ['source' => '/riferimenti/customers/cerca'],
                    'help' => 'Facoltativo: serve a sapere quanto tempo va a ogni cliente.',
                ],
                // Scritto dal sistema (check-in o voce aggiunta a mano), non si sceglie nel form.
                'source' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Come',
                    'defaultRequired' => true,
                    'options' => ['check-in', 'manuale'],
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
                            ['title' => 'Lavoro', 'area' => 'main', 'fields' => ['title', 'customer_id', 'notes']],
                            ['title' => 'Quando e quanto', 'area' => 'sidebar', 'fields' => ['user_id', 'work_date', 'minutes', 'source']],
                        ],
                    ],
                ],
            ],
        ],
        'work_checkins' => [
            'table' => 'work_checkins',
            'fields' => [
                'user_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Persona',
                    'base' => true,
                    'format' => 'integer',
                    'references' => ['table' => 'users', 'column' => 'id'],
                ],
                'checked_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Quando',
                    'base' => true,
                    'format' => 'datetime',
                ],
                'period' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Per',
                    'base' => true,
                    'options' => ['mattina', 'pomeriggio', 'giornata'],
                ],
            ],
        ],
    ],
];
