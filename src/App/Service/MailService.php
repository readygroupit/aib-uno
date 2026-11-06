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
        // Modalita' prova: agli utenti del gestionale si scrive davvero (es.
        // link per la password), a tutti gli altri (clienti) no: la mail va
        // all'indirizzo per le prove, o resta nel registro delle prove.
        $test = $this->container->get(\App\TestMode\TestModeService::class);
        if ($test->simulates('customer_email') && !$this->isUserEmail($to)) {
            $test->record('customer_email', "Email a {$to}: {$subject}", $body, $test->testEmail() ?? $to);
            if ($test->testEmail() === null) {
                $this->log($to, '[PROVA, NON INVIATA] ' . $subject, $body);

                return true;
            }
            $subject = "[PROVA per {$to}] {$subject}";
            $to = $test->testEmail();
        }

        $this->log($to, $subject, $body);

        @mail($to, $subject, $body);

        return true;
    }

    private function isUserEmail(string $email): bool
    {
        try {
            return $this->container->get(\App\Repository\UserRepository::class)->findAll(['email' => strtolower(trim($email))]) !== [];
        } catch (\Throwable) {
            return true;
        }
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
