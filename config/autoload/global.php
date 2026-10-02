<?php

declare(strict_types=1);

/**
 * Config agnostica dall'ambiente, versionata in git.
 * Valori sensibili/specifici della macchina vanno in local.php (vedi local.php.dist).
 */
return [
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=uno;charset=utf8mb4',
        'username' => 'root',
        'password' => '',
    ],
    'memcached' => [
        'host' => '127.0.0.1',
        'port' => 11211,
        'prefix' => 'uno_',
    ],
    // Nome mostrato nell'interfaccia (titolo, Guida): un progetto generato
    // lo riceve nel suo local.php (vedi ProjectProvisioner).
    'app' => [
        'name' => 'Uno',
    ],
    // Dove e come nascono i progetti generati: una cartella per progetto,
    // raggiunta dal vhost jolly zz-uno-projects.conf (*.localhost ->
    // /var/www/projects/<nome>/public), senza toccare Apache ogni volta.
    'provisioning' => [
        'projectsDir' => '/var/www/projects',
        'urlPattern' => 'http://%s.localhost',
        'dbPrefix' => 'prj_',
    ],
    'claude' => [
        'apiKey' => null,
        'model' => 'claude-opus-4-8',
    ],
];
