<?php

declare(strict_types=1);

// Solo per il server integrato di PHP (php -S) in sviluppo: se la richiesta
// punta a un file statico esistente (css/js/immagini), lascialo servire
// cosi' com'e' invece di passare dal front controller. In produzione con
// Apache se ne occupa gia' .htaccess, questo blocco non scatta mai.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $staticFile = __DIR__ . $requestPath;
    if ($requestPath !== '/' && is_file($staticFile)) {
        // I file .js importati da altri .js (import './x.js') non passano
        // mai da un tag <script>/<link> quindi non ricevono mai il '?v='
        // di View::asset() - senza questo header un F5 normale puo'
        // servire dal proprio disco una versione vecchia dello script,
        // anche subito dopo averlo modificato (successo davvero in sessione).
        header('Cache-Control: no-cache');
        return false;
    }
}

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');

require ROOT_PATH . '/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** @var \App\Core\Container $container */
$container = require CONFIG_PATH . '/bootstrap.php';

(new App\Core\Application($container))->run();
