<?php

declare(strict_types=1);

/**
 * Applica le migrazioni in sospeso (db/migrations/pending/*.sql) al
 * database configurato per QUESTO progetto - script indipendente da Uno,
 * stesso principio di bin/run-scheduler.php: usa il bootstrap del
 * progetto (Container, DbService) ma non ha bisogno di un server web, di
 * una sessione autenticata o di una chiamata a Claude. Pensato per girare
 * anche su un server dove "Uno" non e' mai stato presente - i file di
 * migrazione arrivano committati nel repo del progetto, generati altrove
 * (in dev, dove Uno gira davvero) da InstallPackageTool/MigrationWriter.
 *
 * Un file applicato con successo viene spostato in db/migrations/applied/
 * (mai cancellato): quello spostamento E' il tracking di cosa e' gia'
 * stato eseguito - vedi PackageCatalogService::isInstalled(), che legge
 * proprio questa cartella invece di una tabella a parte.
 *
 * Uso: php8.4 bin/migrate.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');

require ROOT_PATH . '/autoload.php';

/** @var \App\Core\Container $container */
$container = require CONFIG_PATH . '/bootstrap.php';

/** @var \App\Service\DbService $db */
$db = $container->get(\App\Service\DbService::class);

$pendingDir = ROOT_PATH . '/db/migrations/pending';
$appliedDir = ROOT_PATH . '/db/migrations/applied';

foreach ([$pendingDir, $appliedDir] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Impossibile creare la directory: {$dir}\n");
        exit(1);
    }
}

$files = glob($pendingDir . '/*.sql') ?: [];
sort($files);

if ($files === []) {
    fwrite(STDOUT, "Nessuna migrazione in sospeso.\n");
    exit(0);
}

$applied = 0;

foreach ($files as $file) {
    $name = basename($file);
    $sql = file_get_contents($file);

    if ($sql === false) {
        fwrite(STDERR, "Impossibile leggere {$name}, interrotto.\n");
        exit(1);
    }

    // Ogni file e' generato da PackageInstaller (mai testo arbitrario):
    // puo' contenere piu' istruzioni DDL concatenate (un package con piu'
    // entity produce piu' CREATE TABLE nello stesso file 'core'), separate
    // qui a mano invece di affidarsi al multi-statement di PDO, che
    // dipende dal driver/dalla configurazione.
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));

    try {
        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }
            $db->pdo()->exec($statement);
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, "FALLITA {$name}: {$e->getMessage()}\n");
        fwrite(STDERR, "Le migrazioni applicate finora restano applicate; questa e quelle dopo restano in sospeso.\n");
        exit(1);
    }

    rename($file, $appliedDir . '/' . $name);
    fwrite(STDOUT, "Applicata: {$name}\n");
    $applied++;
}

fwrite(STDOUT, "Fatto: {$applied} migrazione/i applicata/e.\n");
