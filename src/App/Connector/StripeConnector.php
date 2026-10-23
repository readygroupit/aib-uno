<?php

declare(strict_types=1);

namespace App\Connector;

use App\Payments\PaymentGatewayInterface;

/**
 * Stripe: pagamenti con carta e addebito SEPA sul conto.
 *
 * - checkout(): una pagina di pagamento Stripe (Checkout) per un incasso;
 * - setupAutoPay(): pagina Stripe in cui il cliente salva carta o mandato
 *   SEPA per gli addebiti futuri (mode=setup);
 * - charge(): addebito senza il cliente presente (off_session) sul metodo
 *   salvato, per rate e rinnovi;
 * - verifyWebhook(): la firma di Stripe sulle notifiche (pagato, fallito,
 *   metodo salvato), al posto del token CSRF.
 *
 * Chiave segreta e segreto del webhook nella tabella dei connettori; mai
 * nel codice.
 */
final class StripeConnector extends AbstractApiKeyConnector implements PaymentGatewayInterface
{
    private const API = 'https://api.stripe.com/v1';
    public const WEBHOOK_PATH = '/pagamenti/webhook/stripe';

    public function code(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function supportsAutoPay(): bool
    {
        return true;
    }

    public function setupSpec(): array
    {
        return [
            'intro' => 'Pagamenti con carta e addebito SEPA sul conto, anche automatici per rate e rinnovi. I soldi arrivano sul vostro conto Stripe.',
            'steps' => [
                'Accedi a dashboard.stripe.com (o crea l\'account dell\'agenzia) e completa i dati aziendali e il conto su cui ricevere i pagamenti.',
                'In "Impostazioni", "Metodi di pagamento", attiva Carte e "Addebito diretto SEPA".',
                'In "Sviluppatori", "Chiavi API", copia la "Chiave segreta" (sk_live_..., oppure sk_test_... per provare).',
                'In "Sviluppatori", "Webhook", aggiungi un endpoint con indirizzo ' . self::baseUrl() . self::WEBHOOK_PATH . ' e gli eventi checkout.session.completed, payment_intent.succeeded, payment_intent.payment_failed. Copia il "Segreto di firma" (whsec_...).',
            ],
            'fields' => [
                ['key' => 'api_key', 'label' => 'Chiave segreta', 'type' => 'password', 'placeholder' => 'sk_live_...'],
                ['key' => 'webhook_secret', 'label' => 'Segreto del webhook', 'type' => 'password', 'placeholder' => 'whsec_...'],
            ],
            'note' => 'Con una chiave sk_test_ tutto funziona in prova: nessun soldo vero si muove.',
        ];
    }

    public function test(): bool
    {
        $key = $this->apiKey();
        if ($key === null) {
            return false;
        }
        try {
            $this->verify($key);

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function connect(array $input): string
    {
        $key = trim($input['api_key'] ?? '');
        $secret = trim($input['webhook_secret'] ?? '');
        if (!str_starts_with($key, 'sk_') && !str_starts_with($key, 'rk_')) {
            throw new ConnectorException('La chiave segreta di Stripe inizia con sk_live_ (o sk_test_ per le prove).');
        }
        if ($secret !== '' && !str_starts_with($secret, 'whsec_')) {
            throw new ConnectorException('Il segreto del webhook inizia con whsec_.');
        }

        $summary = $this->verify($key) . ($secret === '' ? ', senza webhook: i pagamenti vanno segnati a mano' : '');
        $this->saveVerified('api_key', ['api_key' => $key], ['webhook_secret' => $secret], $summary);

        return $summary;
    }

    private function verify(string $apiKey): string
    {
        $response = Http::request('GET', self::API . '/balance', ["Authorization: Bearer {$apiKey}"]);
        if ($response['status'] === 401) {
            throw new ConnectorException('Stripe non riconosce la chiave: controlla di aver copiato la chiave segreta (sk_...).');
        }
        if ($response['status'] !== 200) {
            throw new ConnectorException('Stripe non risponde come previsto (codice ' . $response['status'] . ').');
        }

        return str_starts_with($apiKey, 'sk_test_') ? 'modalita\' di prova' : 'pagamenti reali';
    }

    public function checkout(array $request): array
    {
        $customerRef = $request['customerRef'] ?? $this->createCustomer($request['customerName'], $request['email']);
        $methods = array_values(array_intersect($request['methods'] ?: ['card'], ['card', 'sepa_debit']));
        $body = [
            'mode' => 'payment',
            'customer' => $customerRef,
            'success_url' => $request['successUrl'],
            'cancel_url' => $request['cancelUrl'],
            'client_reference_id' => $request['reference'],
            'metadata[reference]' => $request['reference'],
            'payment_intent_data[metadata][reference]' => $request['reference'],
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => 'eur',
            'line_items[0][price_data][unit_amount]' => $request['amountCents'],
            'line_items[0][price_data][product_data][name]' => mb_substr($request['description'], 0, 250),
        ];
        foreach ($methods as $i => $method) {
            $body["payment_method_types[{$i}]"] = $method;
        }
        $session = $this->post('/checkout/sessions', $body);

        return ['url' => (string) $session['url'], 'id' => (string) $session['id'], 'customerRef' => $customerRef];
    }

    /** Pagina Stripe per salvare carta o mandato SEPA per gli addebiti futuri. */
    public function setupAutoPay(string $customerName, ?string $email, ?string $customerRef, string $reference, string $successUrl, string $cancelUrl): array
    {
        $customerRef ??= $this->createCustomer($customerName, $email);
        $session = $this->post('/checkout/sessions', [
            'mode' => 'setup',
            'customer' => $customerRef,
            'currency' => 'eur',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'payment_method_types[0]' => 'card',
            'payment_method_types[1]' => 'sepa_debit',
            'metadata[reference]' => $reference,
            'setup_intent_data[metadata][reference]' => $reference,
        ]);

        return ['url' => (string) $session['url'], 'id' => (string) $session['id'], 'customerRef' => $customerRef];
    }

    /**
     * Addebito sul metodo salvato, senza il cliente. Carta: l'esito e'
     * subito; SEPA: "processing", l'esito arriva col webhook.
     *
     * @return array{status:string, id:string}
     */
    public function charge(string $customerRef, string $paymentMethod, int $amountCents, string $description, string $reference): array
    {
        $intent = $this->post('/payment_intents', [
            'amount' => $amountCents,
            'currency' => 'eur',
            'customer' => $customerRef,
            'payment_method' => $paymentMethod,
            'off_session' => 'true',
            'confirm' => 'true',
            'description' => mb_substr($description, 0, 250),
            'metadata[reference]' => $reference,
        ], false);

        return ['status' => (string) ($intent['status'] ?? 'failed'), 'id' => (string) ($intent['id'] ?? '')];
    }

    /** Il metodo salvato con una sessione di tipo setup (dal webhook). */
    public function paymentMethodFromSetupIntent(string $setupIntentId): ?string
    {
        $intent = $this->get('/setup_intents/' . rawurlencode($setupIntentId));

        return isset($intent['payment_method']) ? (string) $intent['payment_method'] : null;
    }

    /**
     * Verifica la firma Stripe-Signature (t=...,v1=...): HMAC SHA-256 di
     * "t.corpo" con il segreto del webhook, entro 5 minuti.
     *
     * @return ?array l'evento, o null se la firma non torna
     */
    public function verifyWebhook(string $payload, string $signatureHeader): ?array
    {
        $secret = (string) ($this->meta()['webhook_secret'] ?? '');
        if ($secret === '' || $signatureHeader === '') {
            return null;
        }
        $parts = [];
        foreach (explode(',', $signatureHeader) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$key][] = $value;
        }
        $timestamp = (int) ($parts['t'][0] ?? 0);
        if ($timestamp === 0 || abs(time() - $timestamp) > 300) {
            return null;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                $event = json_decode($payload, true);

                return is_array($event) ? $event : null;
            }
        }

        return null;
    }

    private function createCustomer(string $name, ?string $email): string
    {
        $customer = $this->post('/customers', array_filter(['name' => $name, 'email' => $email]));

        return (string) $customer['id'];
    }

    private function post(string $path, array $body, bool $failOnError = true): array
    {
        $response = Http::request('POST', self::API . $path, [
            'Authorization: Bearer ' . $this->apiKey(),
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query($body), 20);
        if ($failOnError && ($response['status'] >= 300 || $response['json'] === null)) {
            throw new ConnectorException('Stripe: ' . ($response['json']['error']['message'] ?? 'errore ' . $response['status']));
        }

        return $response['json'] ?? [];
    }

    private function get(string $path): array
    {
        $response = Http::request('GET', self::API . $path, ['Authorization: Bearer ' . $this->apiKey()]);

        return $response['json'] ?? [];
    }

    private static function baseUrl(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
