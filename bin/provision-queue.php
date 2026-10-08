<?php

declare(strict_types=1);

/**
 * Cron di ROOT sul server, una volta al minuto (vedi README, "Produzione"):
 *
 *   * * * * * /usr/bin/php8.4 /var/www/aib/prod_uno/bin/provision-queue.php >> /var/log/uno-provisioning.log 2>&1
 *
 * 1. crea i progetti messi in coda da Uno (ProjectProvisioner::runQueued():
 *    codice, database, pacchetti, dati demo) e passa la cartella a www-data;
 * 2. per Uno e per ogni progetto pronto con la cartella al suo posto,
 *    crea vhost e certificato se mancano (SiteInstaller).
 *
 * Senza APPLICATION_ENV vale la config di produzione. Un solo giro alla
 * volta (lock): se il precedente non ha finito, questo esce subito.
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');

require ROOT_PATH . '/autoload.php';

$lock = fopen(ROOT_PATH . '/data/cache/provision-queue.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

/** @var \App\Core\Container $container */
$container = require CONFIG_PATH . '/bootstrap.php';

// Gira come root: log e cache scritti qui devono restare di www-data,
// altrimenti Apache non riesce piu' a scriverci.
register_shutdown_function(static function () use ($container): void {
    $user = $container->get(\App\Core\Config::class)->get('provisioning')['vhost']['webUser'] ?? null;
    if ($user !== null && function_exists('posix_getuid') && posix_getuid() === 0) {
        exec('chown -R ' . escapeshellarg("{$user}:{$user}") . ' ' . escapeshellarg(ROOT_PATH . '/data'));
    }
});

$provisioner = $container->get(\App\Provisioning\ProjectProvisioner::class);
$site = $container->get(\App\Provisioning\SiteInstaller::class);
$projects = $container->get(\App\Repository\ProjectRepository::class);
$settings = $container->get(\App\Core\Config::class)->get('provisioning');
$say = static function (string $line): void {
    echo '[' . date('Y-m-d H:i:s') . "] {$line}\n";
};

foreach ($projects->findAll(['provisioning_status' => 'queued'], 'id ASC') as $project) {
    $say("Creo {$project->slug}...");
    foreach ($provisioner->runQueued($project) as $line) {
        $say("  {$line}");
    }
    $dir = $provisioner->projectDir((string) $project->slug);
    if (!empty($settings['vhost']['webUser']) && is_dir($dir)) {
        exec('chown -R ' . escapeshellarg($settings['vhost']['webUser'] . ':' . $settings['vhost']['webUser']) . ' ' . escapeshellarg($dir));
    }
}

if (!$site->enabled()) {
    exit(0);
}

$sites = [['uno', ROOT_PATH, $provisioner->projectUrl('uno'), null]];
foreach ($projects->findAll(['provisioning_status' => 'ready'], 'id ASC') as $project) {
    $slug = (string) $project->slug;
    $sites[] = [$slug, $provisioner->projectDir($slug), $provisioner->projectUrl($slug), $project];
}

foreach ($sites as [$slug, $dir, $url, $project]) {
    try {
        $log = $site->ensure($slug, $dir, $url);
    } catch (\Throwable $e) {
        $log = [$e->getMessage()];
    }
    foreach ($log as $line) {
        $say($line);
    }
    if ($log !== [] && $project !== null) {
        $projects->update((int) $project->id, ['provisioning_log' => trim($project->provisioningLog . "\n" . implode("\n", $log))]);
    }
}
