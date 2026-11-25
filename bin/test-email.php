<?php

declare(strict_types=1);

/**
 * Prova l'invio delle email con la configurazione di questo server.
 * Uso: php bin/test-email.php indirizzo@esempio.it
 * Dice se parte via SMTP (e con quale errore, se non parte) o con mail().
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
require ROOT_PATH . '/autoload.php';
$container = require CONFIG_PATH . '/bootstrap.php';

$to = $argv[1] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/test-email.php indirizzo@esempio.it\n");
    exit(1);
}
$smtp = (array) (($container->get(App\Core\Config::class)->get('mail') ?? [])['smtp'] ?? []);
$via = trim((string) ($smtp['host'] ?? '')) !== '' ? 'SMTP ' . $smtp['host'] . ':' . ($smtp['port'] ?? 587) : 'mail() del server (SMTP non configurato)';
echo "Invio a {$to} via {$via}...\n";
$mail = $container->get(App\Service\MailService::class);
$ok = $mail->send($to, 'Prova email da ' . (defined('APP_NAME') ? APP_NAME : 'il gestionale'), "Se leggi questa email, l'invio funziona.\n\nInviata il " . date('d/m/Y H:i') . '.');
if ($ok) {
    echo "Fatto: email inviata" . (str_starts_with($via, 'mail()') ? " a mail() (controlla che il server la consegni davvero)" : '') . ".\n";
    exit(0);
}
echo 'Non inviata: ' . ($mail->lastError ?? 'errore sconosciuto') . "\n";
exit(1);
