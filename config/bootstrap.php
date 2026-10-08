<?php

declare(strict_types=1);

use App\Connector\ConnectorRegistry;
use App\Core\Config;
use App\Core\Container;
use App\Event\EventDispatcher;
use App\Prompt\PromptToolRegistry;

// global.php (per ambiente, vedi APPLICATION_ENV) -> project.php (solo nei
// progetti generati da Uno: nome, slug, database) -> local.php (facoltativo,
// solo sul PC: in produzione non esiste).
$config = require CONFIG_PATH . '/autoload/global.php';
foreach (['project.php', 'local.php'] as $file) {
    if (is_file(CONFIG_PATH . '/autoload/' . $file)) {
        $config = array_replace_recursive($config, require CONFIG_PATH . '/autoload/' . $file);
    }
}

// Nome dell'applicazione per i template (titolo, Guida): 'Uno' qui, il nome
// del cliente in un progetto generato (vedi ProjectProvisioner).
if (!defined('APP_NAME')) {
    define('APP_NAME', (string) ($config['app']['name'] ?? 'Uno'));
}

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

// La home ha un cruscotto (gestionale) o e' vuota (Uno)? Serve al pulsante
// "casa" del prompt (layout.phtml/hero.js): con il cruscotto ci torna via
// prompt, senza ricaricare; senza, ricarica la home vuota.
if (!defined('APP_HAS_DASHBOARD')) {
    define('APP_HAS_DASHBOARD', $container->get(PromptToolRegistry::class)->has('show_home_dashboard'));
}

// Stesso principio di events.php, per i connettori verso sistemi esterni.
$connectorsFile = CONFIG_PATH . '/connectors.php';
if (is_file($connectorsFile)) {
    (require $connectorsFile)($container->get(ConnectorRegistry::class));
}

return $container;
