<?php

declare(strict_types=1);

/**
 * Config versionata in git, divisa per ambiente come nei progetti di Core:
 * APPLICATION_ENV = "localhost" (SetEnv dei vhost del PC) e' sviluppo,
 * qualunque altro valore - o nessuno, come il cron sul server - e'
 * produzione. In produzione non c'e' local.php: vale solo questo file
 * (piu' config/autoload/project.php nei progetti generati da Uno).
 * Dal terminale del PC: APPLICATION_ENV=localhost php8.4 bin/...
 */
$isDev = getenv('APPLICATION_ENV') === 'localhost';

return [
    'env' => $isDev ? 'localhost' : 'production',
    'db' => $isDev ? [
        'dsn' => 'mysql:host=127.0.0.1;dbname=dev_aib_uno;charset=utf8mb4',
        'username' => 'root',
        'password' => 'root',
    ] : [
        'dsn' => 'mysql:host=127.0.0.1;dbname=prod_aib_uno;charset=utf8mb4',
        'username' => 'admin',
        'password' => 'rG55$lu!ga',
    ],
    'memcached' => [
        'host' => '127.0.0.1',
        'port' => 11211,
        'prefix' => 'uno_',
    ],
    // Nome mostrato nell'interfaccia (titolo, Guida): un progetto generato
    // lo riceve nel suo config/autoload/project.php (vedi ProjectProvisioner).
    'app' => [
        'name' => 'Uno',
        'slug' => null,
    ],
    // Dove e come nascono i progetti generati (vedi ProjectProvisioner e
    // bin/provision-queue.php). %s = slug del progetto.
    'provisioning' => ($isDev ? [
        'dirPattern' => '/var/www/aib/dev_%s',
        // Collegamento per il vhost jolly zz-uno-projects.conf
        // (*.localhost -> /var/www/projects/<slug>/public): nessun vhost da creare.
        'linkDir' => '/var/www/projects',
        'dbPattern' => 'dev_aib_%s',
        'urlPattern' => 'http://%s.localhost',
        // Sul PC il progetto nasce subito, dentro la richiesta.
        'runInRequest' => true,
        'vhost' => null,
    ] : [
        'dirPattern' => '/var/www/aib/prod_%s',
        'linkDir' => null,
        'dbPattern' => 'prod_aib_%s',
        'urlPattern' => 'https://%s.aibrains.it',
        // In produzione Uno mette il progetto in coda: lo crea il cron di
        // root (bin/provision-queue.php), che fa anche vhost e certificato.
        'runInRequest' => false,
        'vhost' => [
            'prototype' => 'packages/provisioning/vhost/aibrains.conf',
            'sitesDir' => '/etc/apache2/sites-available',
            'filePattern' => 'prod_aib_%s.conf',
            'certbot' => '/snap/bin/certbot',
            'email' => 'readygroupit@gmail.com',
            'webUser' => 'www-data',
        ],
    ]) + [
        // Chiave del link di primo accesso (slug cifrato, vedi
        // App\Support\FirstAccessToken): uguale su Uno e sui progetti, che
        // ricevono questo file. Cambiarla invalida i link non ancora usati.
        'tokenKey' => 'b4a97a976da9a30f8870c93023cea5db16f47927addf7e5bce0faa2ca245136d',
    ],
    'claude' => [
        'apiKey' => null,
        'model' => 'claude-opus-4-8',
    ],
];
