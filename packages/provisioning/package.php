<?php

declare(strict_types=1);

/**
 * Il motore che genera progetti nuovi (vedi App\Provisioning\
 * ProjectProvisioner): una cartella, un database, i pacchetti scelti e,
 * se il preset li ha, i dati demo. Pensato per stare su Uno, non nei
 * progetti generati - per questo non viene mai copiato dentro uno di loro
 * a meno di sceglierlo esplicitamente (un mini-configuratore per il
 * cliente: vedi README, "Cosa manca").
 *
 * presets/<chiave>/preset.php: punto di partenza pronto (nome, pacchetti,
 * testi della Guida); presets/<chiave>/demo.sql: dati di esempio.
 */
return [
    'name' => 'provisioning',
    'label' => 'Generatore di progetti',
    'category' => 'sistema',
    'description' => 'Crea progetti nuovi: cartella, database, pacchetti e dati demo.',
    'dependsOn' => [],
    'selectableDirectly' => false,
    'entities' => [
        'projects' => [
            'table' => 'projects',
            'fields' => [
                'name' => ['sql' => 'VARCHAR(120)', 'label' => 'Nome', 'base' => true],
                'slug' => ['sql' => 'VARCHAR(60)', 'label' => 'Identificativo', 'base' => true],
                'db_name' => ['sql' => 'VARCHAR(64)', 'label' => 'Database', 'base' => true],
                'path' => ['sql' => 'VARCHAR(255)', 'label' => 'Cartella', 'base' => true],
                'url' => ['sql' => 'VARCHAR(255)', 'label' => 'Indirizzo', 'base' => true],
                'preset' => ['sql' => 'VARCHAR(60)', 'label' => 'Preset', 'base' => true, 'nullable' => true],
                'packages' => ['sql' => 'TEXT', 'label' => 'Pacchetti (JSON)', 'base' => true],
                'with_demo_data' => ['sql' => 'TINYINT UNSIGNED', 'label' => 'Dati demo', 'base' => true, 'format' => 'integer'],
                // queued (in coda, produzione) | running | ready | failed
                'provisioning_status' => ['sql' => "VARCHAR(20) DEFAULT 'ready'", 'label' => 'Stato creazione', 'base' => true],
                'provisioning_log' => ['sql' => 'TEXT', 'label' => 'Log creazione', 'base' => true, 'nullable' => true],
            ],
        ],
    ],
];
