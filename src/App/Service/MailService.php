<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;

/**
 * Invio email volutamente minimale (zero dipendenze): tenta mail() nativo,
 * ma logga sempre il contenuto in data/logs/mail.log, che resta la fonte
 * di verita' in sviluppo (es. per leggere il link di reset password senza
 * un vero server SMTP configurato). Quando servira' una consegna reale,
 * questo e' il punto da collegare a un connettore SMTP nella config
 * (stesso meccanismo gia' usato per Klarna).
 */
final class MailService
{
    public function __construct(private readonly Container $container)
    {
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $this->log($to, $subject, $body);

        @mail($to, $subject, $body);

        return true;
    }

    private function log(string $to, string $subject, string $body): void
    {
        $line = sprintf(
            "[%s] To: %s | Subject: %s\n%s\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            str_repeat('-', 40),
            $body
        );

        @file_put_contents(ROOT_PATH . '/data/logs/mail.log', $line, FILE_APPEND);
    }
}
