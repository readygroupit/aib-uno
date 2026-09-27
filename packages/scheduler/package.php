<?php

declare(strict_types=1);

/**
 * Esecuzione programmata di operazioni - generico, non legato all'IA.
 * 'job_type' e' il punto di estensione: un codice che App\Job\JobHandlerRegistry
 * risolve in un handler concreto (stessa idea del registro componenti JS di
 * Fase 2 - il pacchetto non sa e non deve sapere cosa fanno i job, sa solo
 * quando farli partire).
 *
 * Due entity per una ragione di pattern di accesso, non di opzionalita'
 * (lo stesso criterio gia' applicato a 'communications'): 'scheduled_jobs'
 * e' la definizione, letta di continuo dal motore per capire cosa e'
 * dovuto ora; 'scheduled_job_runs' e' un log di sola scrittura che cresce
 * a ogni esecuzione, consultato solo per storico/debug - tabelle separate
 * con pattern di lettura/scrittura completamente diversi.
 *
 * 'interval_minutes' assente/NULL = job una tantum (eseguito una volta e
 * poi disattivato, status=0 - il soft delete del framework fa gia' da
 * "job completato", non serve un flag separato). Valorizzato = ricorrente
 * ogni N minuti. Scelta deliberata rispetto a un'espressione cron
 * completa: copre gia' i casi reali del documento Assilevi (controllo
 * giornaliero, ogni N ore) senza scrivere un parser cron da zero (uno
 * non ha dipendenze esterne per farlo). Se in futuro servisse davvero
 * una sintassi cron completa, si aggiunge un campo 'cron_expression' in
 * coda, non si tocca questo.
 */
return [
    'name' => 'scheduler',
    'label' => 'Pianificazione',
    'category' => 'sistema',
    'description' => 'Esecuzione programmata di operazioni ricorrenti.',
    'dependsOn' => [],
    'selectableDirectly' => true,
    'entities' => [
        'scheduled_jobs' => [
            'table' => 'scheduled_jobs',
            'fields' => [
                'job_type' => [
                    'sql' => 'VARCHAR(100)',
                    'label' => 'Tipo di operazione',
                    'base' => true,
                ],
                'payload' => [
                    'sql' => 'TEXT',
                    'label' => 'Parametri (JSON)',
                    'base' => true,
                ],
                'entity_type' => [
                    'sql' => 'VARCHAR(50)',
                    'label' => 'Tipo entita collegata',
                    'defaultRequired' => false,
                ],
                'entity_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Id entita collegata',
                    'defaultRequired' => false,
                ],
                'run_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Prossima esecuzione',
                    'base' => true,
                ],
                'interval_minutes' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Intervallo di ripetizione (minuti)',
                    'defaultRequired' => false,
                ],
                'last_run_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Ultima esecuzione',
                    'base' => true,
                    'nullable' => true,
                ],
                'last_run_status' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Esito ultima esecuzione',
                    'base' => true,
                    'nullable' => true,
                ],
            ],
        ],
        'scheduled_job_runs' => [
            'table' => 'scheduled_job_runs',
            'fields' => [
                'scheduled_job_id' => [
                    'sql' => 'INT UNSIGNED',
                    'label' => 'Job',
                    'base' => true,
                    'references' => ['table' => 'scheduled_jobs', 'column' => 'id'],
                ],
                'started_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Avviata il',
                    'base' => true,
                ],
                'finished_at' => [
                    'sql' => 'DATETIME',
                    'label' => 'Conclusa il',
                    'base' => true,
                ],
                'run_status' => [
                    'sql' => 'VARCHAR(20)',
                    'label' => 'Esito',
                    'base' => true,
                ],
                'result' => [
                    'sql' => 'TEXT',
                    'label' => 'Esito dettagliato / errore',
                    'base' => true,
                    'nullable' => true,
                ],
            ],
        ],
    ],
];
