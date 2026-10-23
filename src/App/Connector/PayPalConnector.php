<?php

declare(strict_types=1);

namespace App\Connector;

use App\Payments\PaymentGatewayInterface;

/**
 * PayPal: pagamento di un incasso con un ordine (Orders API v2). Il
 * cliente approva su PayPal, torna su /pagamenti/paypal/ritorno e li'
 * l'ordine si incassa (capture): solo allora l'incasso risulta pagato.
 *
 * Gli addebiti automatici con PayPal (abbonamenti) non sono ancora
 * collegati: per il rinnovo automatico si usa Stripe.
 */
final class PayPalConnector extends AbstractApiKeyConnector implements PaymentGatewayInterface
{
    public const RETURN_PATH = '/pagamenti/paypal/ritorno';

    public function code(): string
    {
        return 'paypal';
    }

    public function label(): string
    {
        return 'PayPal';
    }

    public function supportsAutoPay(): bool
    {
        return false;
    }

    public function test(): bool
    {
        try {
            $this->token();

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        return [
            'intro' => 'Il cliente paga rate e fatture con il suo conto PayPal o una carta, dalla pagina di PayPal.',
            'steps' => [
                'Accedi a developer.paypal.com con l\'account PayPal Business dell\'agenzia.',
                'In "Apps & Credentials" scegli "Live" (o "Sandbox" per le prove) e crea un\'app.',
                'Copia "Client ID" e "Secret" nei campi qui sotto.',
            ],
            'fields' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'placeholder' => 'A...'],
                ['key' => 'api_key', 'label' => 'Secret', 'type' => 'password', 'placeholder' => 'E...'],
                ['key' => 'mode', 'label' => 'Ambiente', 'type' => 'select', 'options' => [['value' => 'live', 'label' => 'Pagamenti reali'], ['value' => 'sandbox', 'label' => 'Prova (sandbox)']]],
            ],
            'note' => 'Il pagamento risulta incassato quando il cliente torna da PayPal e l\'ordine viene confermato.',
        ];
    }

    public function connect(array $input): string
    {
        $clientId = trim($input['client_id'] ?? '');
        $secret = trim($input['api_key'] ?? '');
        $mode = ($input['mode'] ?? 'live') === 'sandbox' ? 'sandbox' : 'live';
        if ($clientId === '' || $secret === '') {
            throw new ConnectorException('Servono Client ID e Secret.');
        }
        $this->requestToken($clientId, $secret, $mode);
        $summary = $mode === 'sandbox' ? 'modalita\' di prova (sandbox)' : 'pagamenti reali';
        $this->saveVerified('api_key', ['api_key' => $secret], ['client_id' => $clientId, 'mode' => $mode], $summary);

        return $summary;
    }

    public function checkout(array $request): array
    {
        $order = $this->call('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $request['reference'],
                'custom_id' => $request['reference'],
                'description' => mb_substr($request['description'], 0, 120),
                'amount' => ['currency_code' => 'EUR', 'value' => number_format($request['amountCents'] / 100, 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => $request['successUrl'],
                'cancel_url' => $request['cancelUrl'],
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'brand_name' => mb_substr($request['customerName'], 0, 120),
            ]]],
        ]);
        $url = null;
        foreach ($order['links'] ?? [] as $link) {
            if (in_array($link['rel'] ?? '', ['payer-action', 'approve'], true)) {
                $url = (string) $link['href'];
            }
        }
        if ($url === null) {
            throw new ConnectorException('PayPal non ha restituito il link di pagamento.');
        }

        return ['url' => $url, 'id' => (string) $order['id'], 'customerRef' => null];
    }

    /**
     * Incassa un ordine approvato dal cliente.
     *
     * @return array{completed:bool, reference:?string, captureId:?string}
     */
    public function capture(string $orderId): array
    {
        $result = $this->call('POST', '/v2/checkout/orders/' . rawurlencode($orderId) . '/capture', new \stdClass());
        $unit = $result['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];

        return [
            'completed' => ($result['status'] ?? '') === 'COMPLETED',
            'reference' => $unit['reference_id'] ?? ($capture['custom_id'] ?? null),
            'captureId' => $capture['id'] ?? null,
        ];
    }

    private function call(string $method, string $path, array|\stdClass $body): array
    {
        $response = Http::request($method, $this->baseApi() . $path, [
            'Authorization: Bearer ' . $this->token(),
            'Content-Type: application/json',
            'PayPal-Request-Id: ' . bin2hex(random_bytes(12)),
        ], json_encode($body), 20);
        if ($response['status'] >= 300) {
            $detail = $response['json']['details'][0]['description'] ?? $response['json']['message'] ?? 'errore ' . $response['status'];
            throw new ConnectorException("PayPal: {$detail}");
        }

        return $response['json'] ?? [];
    }

    private function token(): string
    {
        $meta = $this->meta();

        return $this->requestToken((string) ($meta['client_id'] ?? ''), (string) $this->apiKey(), (string) ($meta['mode'] ?? 'live'));
    }

    private function requestToken(string $clientId, string $secret, string $mode): string
    {
        $response = Http::request('POST', $this->baseApi($mode) . '/v1/oauth2/token', [
            'Authorization: Basic ' . base64_encode("{$clientId}:{$secret}"),
            'Content-Type: application/x-www-form-urlencoded',
        ], 'grant_type=client_credentials');
        if ($response['status'] !== 200 || !isset($response['json']['access_token'])) {
            throw new ConnectorException('PayPal non accetta queste credenziali: controlla Client ID, Secret e ambiente (reale o sandbox).');
        }

        return (string) $response['json']['access_token'];
    }

    private function baseApi(?string $mode = null): string
    {
        $mode ??= (string) ($this->meta()['mode'] ?? 'live');

        return $mode === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }
}
