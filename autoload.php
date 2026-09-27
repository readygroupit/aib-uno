<?php

declare(strict_types=1);

/**
 * Autoloader nostro, senza Composer: mappa namespace -> cartella e usa
 * spl_autoload_register() (nativo di PHP) per caricare la classe giusta
 * al momento giusto. Risoluzione dinamica: nessun file da rigenerare
 * quando si aggiunge una classe, basta che sia nel posto giusto.
 *
 * Per aggiungere un nuovo modulo: aggiungere una riga alla mappa qui sotto.
 */

$namespaceMap = [
    'App\\' => __DIR__ . '/src/App/',
    'Index\\' => __DIR__ . '/module/Index/src/',
    'Auth\\' => __DIR__ . '/module/Auth/src/',
];

spl_autoload_register(static function (string $class) use ($namespaceMap): void {
    foreach ($namespaceMap as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require $file;
        }

        return;
    }
});
