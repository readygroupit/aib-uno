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
    'claude' => [
        'apiKey' => null,
        'model' => 'claude-opus-4-8',
    ],
];
