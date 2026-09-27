<?php

declare(strict_types=1);

/**
 * Aggrega le route dichiarate da ogni modulo (module/<Nome>/config/routes.php).
 * Nessuna scansione di controller/reflection: ogni modulo dichiara esplicitamente
 * le proprie route.
 */
$routes = [];
foreach (glob(ROOT_PATH . '/module/*/config/routes.php') as $file) {
    $routes = array_merge($routes, require $file);
}

return $routes;
