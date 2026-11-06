<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Connector\WhatsAppConnector;
use App\Core\Container;
use App\Model\Customer;
use App\Repository\CustomerRepository;
use App\TestMode\TestModeService;

/**
 * Cosa si manda a un cliente (link del preventivo, area cliente, contenuti
 * da approvare, link di pagamento) e come: email (dal proprio programma di
 * posta), WhatsApp dal numero aziendale (WhatsAppConnector) o, senza
 * collegamento, WhatsApp aperto sul proprio dispositivo con il testo pronto.
 *
 * In modalita' prova (TestModeService) email e WhatsApp vanno all'email e
 * al numero per le prove, e la finestra lo dice: il cliente vero non
 * riceve niente.
 */
final class CustomerMessageService
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * La finestra "link per il cliente" (accounts-ui.js shareModal) con i
     * destinatari giusti per email e WhatsApp.
     *
     * @param array<string, mixed> $share title, intro, url, subject, text
     * @return array<string, mixed>
     */
    public function share(?Customer $customer, array $share): array
    {
        $test = $this->container->get(TestModeService::class);
        $phone = $customer !== null ? self::phoneOf($customer) : null;
        $share['email'] = $customer?->email ?: null;
        $share['customerId'] = $customer?->id;
        $notes = [];

        if ($test->simulates('customer_email')) {
            // In prova l'email si prova sempre, anche se il cliente non ha ancora l'indirizzo.
            if ($test->testEmail() !== null) {
                $notes[] = "l'email va a {$test->testEmail()}";
            } elseif ($share['email'] !== null) {
                $notes[] = "l'email non ha un indirizzo per le prove (impostalo in Configurazione, Modalita' prova)";
            }
            $share['email'] = $test->testEmail();
        }
        $whatsappTest = $test->simulates('whatsapp');
        if ($whatsappTest) {
            $phone = $test->testPhone() !== null ? self::normalize($test->testPhone()) : null;
            $notes[] = $phone !== null ? "WhatsApp va al numero per le prove ({$test->testPhone()})" : 'WhatsApp resta solo nel registro delle prove';
        }
        $share['whatsapp'] = [
            // Dal numero aziendale (server) o, senza collegamento, aperto sul dispositivo.
            'api' => $this->whatsapp()?->isConnected() === true || $whatsappTest,
            'action' => $customer !== null ? '/messaggi/whatsapp' : null,
            'phone' => $phone,
        ];
        if ($notes !== []) {
            $share['testNote'] = 'Modalita\' prova: ' . implode('; ', $notes) . '. Il cliente vero non riceve niente.';
        }

        return $share;
    }

    /**
     * Manda il messaggio al cliente dal numero WhatsApp aziendale.
     *
     * @return string cosa e' successo, da mostrare
     */
    public function sendWhatsApp(int $customerId, string $text): string
    {
        $customer = $this->container->get(CustomerRepository::class)->find($customerId);
        if (!$customer instanceof Customer) {
            throw new ConnectorException('Cliente non trovato.');
        }
        $text = trim($text);
        if ($text === '') {
            throw new ConnectorException('Il messaggio e\' vuoto.');
        }
        $test = $this->container->get(TestModeService::class);
        $real = self::phoneOf($customer);

        if ($test->simulates('whatsapp')) {
            $to = $test->testPhone() !== null ? self::normalize($test->testPhone()) : null;
            $test->record('whatsapp', "WhatsApp a {$customer->displayName()}" . ($real !== null ? " (+{$real})" : ''), $text, $to !== null ? '+' . $to : null, 'customers', $customerId);
            if ($to === null || $this->whatsapp()?->isConnected() !== true) {
                return 'Modalita\' prova: messaggio non inviato, e\' nel registro delle prove.';
            }
            $this->whatsapp()->send($to, "[PROVA per {$customer->displayName()}] " . $text);

            return "Modalita' prova: inviato al numero per le prove (+{$to}), non al cliente.";
        }

        if ($real === null) {
            throw new ConnectorException("{$customer->displayName()} non ha un cellulare nella scheda: aggiungilo e riprova.");
        }
        $whatsapp = $this->whatsapp();
        if ($whatsapp === null || !$whatsapp->isConnected()) {
            throw new ConnectorException('WhatsApp non e\' collegato: collegalo dalla Configurazione.');
        }
        $whatsapp->send($real, $text);

        return 'Inviato su WhatsApp a ' . $customer->displayName() . ' (+' . $real . ').'
            . ($whatsapp->hasTemplate() ? '' : ' Senza modello approvato arriva solo se il cliente vi ha scritto nelle ultime 24 ore.');
    }

    /** Il cellulare del cliente (o il telefono), in formato internazionale. */
    public static function phoneOf(Customer $customer): ?string
    {
        foreach ([$customer->mobilePhone ?? null, $customer->phone ?? null] as $number) {
            $normalized = $number !== null ? self::normalize($number) : null;
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /** "333 123 4567" -> "393331234567"; "+39 ..." e "0039 ..." uguale. Null se non sembra un numero. */
    public static function normalize(string $number): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $number);
        if (str_starts_with(trim($number), '00')) {
            $digits = substr($digits, 2);
        } elseif (!str_starts_with(trim($number), '+') && (strlen($digits) === 9 || strlen($digits) === 10)) {
            // Numero italiano senza prefisso (cellulari 3xx, fissi 0xx).
            $digits = '39' . $digits;
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    private function whatsapp(): ?WhatsAppConnector
    {
        $registry = $this->container->get(ConnectorRegistry::class);
        $connector = $registry->has('whatsapp') ? $registry->get('whatsapp') : null;

        return $connector instanceof WhatsAppConnector ? $connector : null;
    }
}
