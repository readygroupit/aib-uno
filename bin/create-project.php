<?php

declare(strict_types=1);

/**
 * Genera un progetto client nuovo a partire dallo stato ATTUALE di uno:
 * copia il codice (framework + tutti i pacchetti - v1 non negozia
 * ancora campo per campo, vedi il commento piu' sotto) in una cartella
 * nuova, crea un database vuoto, vi applica la struttura del database
 * di QUESTO uno (dump dal database vero, non riproducendo i file di
 * migrazione uno per uno - questi ultimi potrebbero essere andati
 * fuori sincrono con qualche ALTER fatto a mano durante lo sviluppo,
 * il database vivo e' l'unica fonte che non mente) + il seed di
 * bootstrap (permessi/utente admin), scrive config/autoload/local.php
 * per il progetto nuovo.
 *
 * v1 clona tutto (nessuna scelta di pacchetti/campi come nella Fase 3
 * del progetto - quella resta per una versione successiva, qui serve
 * prima avere in piedi un progetto vero da cui imparare). Da qui in
 * poi il progetto e' indipendente: aggiornamenti futuri da uno si
 * riportano a mano (o via git) quando serve davvero, mai in automatico
 * - vedi la memoria di progetto sul perche' niente flag condivisi.
 *
 * Uso: php8.4 bin/create-project.php <nome-progetto> <cartella-target> [nome-db]
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');

$projectName = $argv[1] ?? null;
$targetDir = $argv[2] ?? null;
$dbName = $argv[3] ?? $projectName;

if ($projectName === null || $targetDir === null) {
    fwrite(STDERR, "Uso: php8.4 bin/create-project.php <nome-progetto> <cartella-target> [nome-db]\n");
    exit(1);
}

if (is_dir($targetDir)) {
    fwrite(STDERR, "La cartella target esiste gia': {$targetDir}\n");
    exit(1);
}

$safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $dbName);
if ($safeDbName !== $dbName || $safeDbName === '') {
    fwrite(STDERR, "Nome database non valido (solo lettere/numeri/underscore): {$dbName}\n");
    exit(1);
}

$globalConfig = require CONFIG_PATH . '/autoload/global.php';
$localFile = CONFIG_PATH . '/autoload/local.php';
$localConfig = is_file($localFile) ? require $localFile : [];
$config = array_replace_recursive($globalConfig, $localConfig);
$dbConfig = $config['db'];

preg_match('/host=([^;]+)/', $dbConfig['dsn'], $hostMatch);
$host = $hostMatch[1] ?? '127.0.0.1';
preg_match('/dbname=([^;]+)/', $dbConfig['dsn'], $sourceDbMatch);
$sourceDb = $sourceDbMatch[1] ?? 'uno';

function runSqlStatements(PDO $pdo, string $sql): void
{
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $chunk) {
        // Un commento '-- ...' scritto a mano non ha un ';' tutto suo:
        // spezzando solo su ";\n" resta "incollato" all'istruzione SQL
        // vera che lo segue nello stesso blocco. Controllare se il
        // BLOCCO comincia per '--' non basta (l'ha scoperto un test
        // reale: ha saltato interi INSERT solo perche' un commento li
        // precedeva) - vanno tolte le righe di commento una per una e
        // si guarda cosa resta.
        $lines = array_filter(
            explode("\n", $chunk),
            static fn ($line) => !str_starts_with(trim($line), '--') && !str_starts_with(trim($line), '/*')
        );
        $statement = trim(implode("\n", $lines));

        if ($statement === '' || preg_match('/^SET\s/i', $statement) === 1) {
            continue;
        }
        $pdo->exec($statement);
    }
}

fwrite(STDOUT, "1/5 Copio il codice in {$targetDir}...\n");
mkdir($targetDir, 0775, true);
$excludes = ['.git', 'data/logs', 'data/cache', 'nbproject', 'db/migrations/applied', '.gitignore'];
$excludeArgs = implode(' ', array_map(static fn ($e) => '--exclude=' . escapeshellarg($e), $excludes));
$cmd = "rsync -a {$excludeArgs} " . escapeshellarg(rtrim(ROOT_PATH, '/') . '/') . ' ' . escapeshellarg(rtrim($targetDir, '/') . '/');
exec($cmd, $rsyncOut, $rsyncCode);
if ($rsyncCode !== 0) {
    fwrite(STDERR, "Copia fallita:\n" . implode("\n", $rsyncOut) . "\n");
    exit(1);
}
foreach (['data/logs', 'data/cache', 'db/migrations/applied', 'db/migrations/pending'] as $dir) {
    $full = "{$targetDir}/{$dir}";
    if (!is_dir($full)) {
        mkdir($full, 0775, true);
    }
    touch("{$full}/.gitkeep");
}

fwrite(STDOUT, "2/5 Creo il database '{$safeDbName}'...\n");
$rootPdo = new PDO("mysql:host={$host};charset=utf8mb4", $dbConfig['username'], $dbConfig['password']);
$rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

fwrite(STDOUT, "3/5 Applico la struttura (dalla struttura live di '{$sourceDb}')...\n");
$dumpCmd = 'mysqldump --no-defaults --no-data --skip-comments --skip-add-drop-table -h ' . escapeshellarg($host)
    . ' -u ' . escapeshellarg($dbConfig['username'])
    . (($dbConfig['password'] ?? '') !== '' ? ' -p' . escapeshellarg($dbConfig['password']) : '')
    . ' ' . escapeshellarg($sourceDb) . ' 2>/dev/null';
exec($dumpCmd, $dumpOut, $dumpCode);
if ($dumpCode !== 0 || $dumpOut === []) {
    fwrite(STDERR, "Dump della struttura fallito.\n");
    exit(1);
}
$newPdo = new PDO("mysql:host={$host};dbname={$safeDbName};charset=utf8mb4", $dbConfig['username'], $dbConfig['password']);
$newPdo->exec('SET FOREIGN_KEY_CHECKS=0');
runSqlStatements($newPdo, implode("\n", $dumpOut));
$newPdo->exec('SET FOREIGN_KEY_CHECKS=1');

fwrite(STDOUT, "4/5 Popolo i dati di bootstrap (permessi, utente admin)...\n");
runSqlStatements($newPdo, file_get_contents(ROOT_PATH . '/db/seed.sql'));

fwrite(STDOUT, "5/5 Scrivo config/autoload/local.php per il nuovo progetto...\n");
$claudeKey = $config['claude']['apiKey'] ?? null;
$dbBlock = "    'db' => [\n"
    . "        'dsn' => 'mysql:host={$host};dbname={$safeDbName};charset=utf8mb4',\n"
    . "        'username' => " . var_export($dbConfig['username'], true) . ",\n"
    . "        'password' => " . var_export($dbConfig['password'], true) . ",\n"
    . "    ],\n";
$claudeBlock = $claudeKey
    ? "    'claude' => [\n        'apiKey' => " . var_export($claudeKey, true) . ",\n    ],\n"
    : '';
$localPhp = "<?php\n\ndeclare(strict_types=1);\n\n"
    . "/**\n * Credenziali locali (escluso da git). Generato da bin/create-project.php\n"
    . " * per il progetto '{$projectName}'.\n */\n"
    . "return [\n{$dbBlock}{$claudeBlock}];\n";
file_put_contents("{$targetDir}/config/autoload/local.php", $localPhp);

fwrite(STDOUT, "\nFatto. Progetto '{$projectName}' pronto in {$targetDir}, database '{$safeDbName}'.\n");
fwrite(STDOUT, "Accesso: admin / admin123 (da cambiare appena possibile).\n");
fwrite(STDOUT, "Passi manuali restanti: vhost del webserver, 'git init' nella nuova cartella se la vuoi versionata a parte.\n");
