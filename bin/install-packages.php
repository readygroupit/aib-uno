<?php

declare(strict_types=1);

/**
 * Installa uno o piu' pacchetti su QUESTO progetto gia' esistente (anche
 * Uno stesso): migrazioni con tutti i campi del catalogo (come il
 * provisioner), dati di riferimento del pacchetto, permessi al profilo
 * amministratore. Le dipendenze mancanti arrivano da sole.
 *
 * Uso sul PC: APPLICATION_ENV=localhost php8.4 bin/install-packages.php customers services
 * In produzione le migrazioni arrivano gia' applicate da git: li' si usa
 * lo script SQL in public/tmp preparato insieme.
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');

require ROOT_PATH . '/autoload.php';

use App\Package\MigrationRunner;
use App\Package\MigrationWriter;
use App\Package\PackageCatalogService;
use App\Package\PackageInstallerRunner;
use App\Package\PackageManifest;
use App\Repository\PackagePermissionRepository;
use App\Service\DbService;

/** @var \App\Core\Container $container */
$container = require CONFIG_PATH . '/bootstrap.php';

$requested = array_slice($argv, 1);
if ($requested === []) {
    fwrite(STDERR, "Indica almeno un pacchetto, es.: php8.4 bin/install-packages.php customers\n");
    exit(1);
}

$catalog = $container->get(PackageCatalogService::class);
foreach ($requested as $name) {
    if (!$catalog->exists($name)) {
        fwrite(STDERR, "Pacchetto sconosciuto: {$name}\n");
        exit(1);
    }
}

$fullSelection = static function (string $package): array {
    $selection = [];
    foreach ((new PackageManifest($package))->entities() as $entityKey => $entity) {
        foreach ($entity['fields'] as $fieldKey => $field) {
            if (!($field['base'] ?? false)) {
                $selection[$entityKey][$fieldKey] = 'optional';
            }
        }
    }

    return $selection;
};

// Le dipendenze dai campi scelti possono portare altri pacchetti: si
// risolve finche' l'elenco non smette di crescere (come il provisioner).
$runner = new PackageInstallerRunner();
$order = $requested;
do {
    $selections = [];
    foreach ($order as $package) {
        $selections[$package] = $fullSelection($package);
    }
    $previous = $order;
    $order = $runner->resolveOrder($selections);
} while (count($order) !== count($previous));

$installed = PackageCatalogService::installedNames();
$toInstall = array_values(array_filter($order, static fn (string $p) => !in_array($p, $installed, true)));
if ($toInstall === []) {
    fwrite(STDOUT, "Gia' installati: " . implode(', ', $order) . "\n");
    exit(0);
}

$pdo = $container->get(DbService::class)->pdo();
$container->get(MigrationWriter::class)->writeBatch($toInstall, array_intersect_key($selections, array_flip($toInstall)));
$applied = (new MigrationRunner())->apply($pdo, ROOT_PATH . '/db/migrations/pending', ROOT_PATH . '/db/migrations/applied');
fwrite(STDOUT, 'Migrazioni applicate: ' . count($applied) . "\n");

$permissions = $container->get(PackagePermissionRepository::class);
foreach ($toInstall as $package) {
    $seed = ROOT_PATH . "/packages/{$package}/seed.sql";
    if (is_file($seed) && class_exists(\App\Provisioning\SqlScript::class)) {
        \App\Provisioning\SqlScript::run($pdo, (string) file_get_contents($seed));
    }
    $manifest = new PackageManifest($package);
    // Dati di riferimento (geo) o di sistema (connettori, scheduler): niente
    // schermate proprie, niente permessi (li governa users.manage).
    $created = $manifest->selectableDirectly() && $manifest->category() !== 'sistema'
        ? $permissions->ensure($package, $manifest->label(), 1, $manifest->permissions())
        : 0;
    fwrite(STDOUT, "Installato: {$package} ({$created} permessi nuovi)\n");
}

fwrite(STDOUT, "Fatto. Chi e' collegato deve rientrare per avere i permessi nuovi.\n");
