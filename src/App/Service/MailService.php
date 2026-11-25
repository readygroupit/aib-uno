<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;

/**
 * Invio email. Con il server SMTP configurato (config 'mail.smtp', in
 * secrets.php) parte via SMTP con PHPMailer, come nei progetti core e
 * readyecommerce; altrimenti con la mail() del server. Ogni email resta
 * anche in data/logs/mail.log, con l'esito: e' la fonte di verita' in
 * sviluppo (es. il link di reset password) e il primo posto da guardare se
 * un'email non arriva. Prova dal server: php bin/test-email.php indirizzo.
 */
final class MailService
{
    public function __construct(private readonly Container $container)
    {
    }

    public function send(string $to, string $subject, string $body): bool
    {
        // Modalita' prova: con l'indirizzo per le prove impostato, TUTTE le
        // email (clienti e colleghi) vanno li', con il destinatario vero
        // nell'oggetto. Senza indirizzo: ai clienti non parte niente (resta nel
        // registro delle prove), ai colleghi si scrive davvero. Inviti e
        // password non passano di qui: sendDirect(), arrivano sempre alla persona.
        $test = $this->container->get(\App\TestMode\TestModeService::class);
        if ($test->simulates('customer_email') && ($test->testEmail() !== null || !$this->isUserEmail($to))) {
            $test->record('customer_email', "Email a {$to}: {$subject}", $body, $test->testEmail() ?? $to);
            if ($test->testEmail() === null) {
                $this->log($to, '[PROVA, NON INVIATA] ' . $subject, $body);

                return true;
            }
            $subject = "[PROVA per {$to}] {$subject}";
            $to = $test->testEmail();
        }

        return $this->sendDirect($to, $subject, $body);
    }

    /**
     * Invia davvero, senza la modalita' prova: inviti e link per la password
     * (devono arrivare alla persona, vedi AuthService::requestPasswordReset())
     * e bin/test-email.php (la prova dell'SMTP parte anche in modalita' prova).
     */
    public function sendDirect(string $to, string $subject, string $body): bool
    {
        $this->lastError = null;
        $smtp = $this->smtpSettings();
        if ($smtp === null) {
            $this->log($to, $subject, $body);
            @mail($to, $subject, $body);

            return true;
        }

        $error = $this->sendSmtp($smtp, $to, $subject, $body);
        $this->log($to, ($error === null ? '' : '[NON INVIATA: ' . $error . '] ') . $subject, $body);
        if ($error !== null) {
            error_log("MailService: email a {$to} non inviata: {$error}");
        }

        return $error === null;
    }

    /** L'ultimo errore SMTP (per bin/test-email.php e per chi vuole dirlo all'utente). */
    public ?string $lastError = null;

    /** @return array{host:string,port:int,secure:string,username:string,password:string,from:string,fromName:string}|null */
    private function smtpSettings(): ?array
    {
        $config = $this->container->get(\App\Core\Config::class)->get('mail') ?? [];
        $smtp = (array) ($config['smtp'] ?? []);
        if (trim((string) ($smtp['host'] ?? '')) === '') {
            return null;
        }
        $from = trim((string) ($config['from']['address'] ?? '')) ?: (string) ($smtp['username'] ?? '');

        return [
            'host' => (string) $smtp['host'],
            'port' => (int) ($smtp['port'] ?? 587),
            'secure' => (string) ($smtp['secure'] ?? 'tls'),
            'username' => (string) ($smtp['username'] ?? ''),
            'password' => (string) ($smtp['password'] ?? ''),
            'from' => $from,
            'fromName' => trim((string) ($config['from']['name'] ?? '')) ?: (defined('APP_NAME') ? APP_NAME : ''),
        ];
    }

    /** @return string|null l'errore, null se e' partita */
    private function sendSmtp(array $smtp, string $to, string $subject, string $body): ?string
    {
        $this->lastError = null;
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->Host = $smtp['host'];
            $mail->Port = $smtp['port'];
            $mail->SMTPAuth = $smtp['username'] !== '';
            $mail->Username = $smtp['username'];
            $mail->Password = $smtp['password'];
            if (in_array($smtp['secure'], ['tls', 'ssl'], true)) {
                $mail->SMTPSecure = $smtp['secure'] === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }
            $mail->Timeout = 20;
            $mail->setFrom($smtp['from'], $smtp['fromName']);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            // Testo semplice, piu' una versione HTML leggibile (a capo e link).
            $mail->isHTML(true);
            $html = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
            $html = (string) preg_replace('#(https?://[^\s<]+)#', '<a href="$1">$1</a>', $html);
            $mail->Body = '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#12333f">' . $html . '</div>';
            $mail->AltBody = $body;
            $mail->send();

            return null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return $this->lastError;
        }
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
