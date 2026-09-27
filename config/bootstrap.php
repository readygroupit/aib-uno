<?php

declare(strict_types=1);

use App\Connector\ConnectorRegistry;
use App\Core\Config;
use App\Core\Container;
use App\Event\EventDispatcher;
use App\Prompt\PromptToolRegistry;

$globalConfig = require CONFIG_PATH . '/autoload/global.php';
$localFile = CONFIG_PATH . '/autoload/local.php';
$localConfig = is_file($localFile) ? require $localFile : [];

$config = array_replace_recursive($globalConfig, $localConfig);

$container = new Container();
$container->set(Config::class, static fn () => new Config($config));

// config/events.php e' opzionale (un progetto senza eventi da ascoltare
// non ne ha bisogno): se presente deve restituire una callable che
// registra i listener sull'EventDispatcher del container - stesso
// principio della registrazione handler in bin/run-scheduler.php, ma
// qui deve avvenire ad ogni bootstrap (anche le richieste HTTP passano
// da AbstractRepository, non solo il cron).
$eventsFile = CONFIG_PATH . '/events.php';
if (is_file($eventsFile)) {
    (require $eventsFile)($container->get(EventDispatcher::class), $container);
}

// Stesso principio di events.php, per le capacita' del prompt.
$promptToolsFile = CONFIG_PATH . '/prompt-tools.php';
if (is_file($promptToolsFile)) {
    (require $promptToolsFile)($container->get(PromptToolRegistry::class));
}

// Stesso principio di events.php, per i connettori verso sistemi esterni.
$connectorsFile = CONFIG_PATH . '/connectors.php';
if (is_file($connectorsFile)) {
    (require $connectorsFile)($container->get(ConnectorRegistry::class));
}

return $container;
