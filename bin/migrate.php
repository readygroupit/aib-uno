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

try {
    $applied = (new \App\Package\MigrationRunner())->apply(
        $db->pdo(),
        ROOT_PATH . '/db/migrations/pending',
        ROOT_PATH . '/db/migrations/applied'
    );
} catch (\RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    fwrite(STDERR, "Le migrazioni applicate finora restano applicate; questa e quelle dopo restano in sospeso.\n");
    exit(1);
}

if ($applied === []) {
    fwrite(STDOUT, "Nessuna migrazione in sospeso.\n");
    exit(0);
}

foreach ($applied as $name) {
    fwrite(STDOUT, "Applicata: {$name}\n");
}
fwrite(STDOUT, 'Fatto: ' . count($applied) . " migrazione/i applicata/e.\n");
