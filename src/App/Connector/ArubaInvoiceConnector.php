<?php

declare(strict_types=1);

namespace App\Connector;

use App\Invoicing\InvoiceProviderInterface;
use App\Invoicing\InvoicingException;
use App\Model\Invoice;

/**
 * Aruba Fatturazione Elettronica: si manda l'XML FatturaPA cosi' com'e'
 * (Aruba lo firma e lo inoltra allo SdI). Credenziali del servizio di
 * fatturazione Aruba; ambiente demo per le prove.
 */
final class ArubaInvoiceConnector extends AbstractApiKeyConnector implements InvoiceProviderInterface
{
    public function code(): string
    {
        return 'aruba_invoices';
    }

    public function label(): string
    {
        return 'Aruba Fatturazione Elettronica';
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
            'intro' => 'Le fatture elettroniche partono allo SdI tramite il vostro servizio di Fatturazione Elettronica Aruba.',
            'steps' => [
                'Serve il servizio "Fatturazione Elettronica" di Aruba attivo, con l\'accesso alle API (dal pannello Aruba, sezione API).',
                'Inserisci nome utente e password del servizio di fatturazione.',
                'Per provare senza inviare fatture vere scegli l\'ambiente demo.',
            ],
            'fields' => [
                ['key' => 'username', 'label' => 'Nome utente', 'type' => 'text', 'placeholder' => 'ARUBA-...'],
                ['key' => 'api_key', 'label' => 'Password', 'type' => 'password', 'placeholder' => ''],
                ['key' => 'mode', 'label' => 'Ambiente', 'type' => 'select', 'options' => [['value' => 'prod', 'label' => 'Reale'], ['value' => 'demo', 'label' => 'Demo (prove)']]],
            ],
            'note' => 'Si verifica solo che Aruba accetti le credenziali.',
        ];
    }

    public function connect(array $input): string
    {
        $username = trim($input['username'] ?? '');
        $password = (string) ($input['api_key'] ?? '');
        $mode = ($input['mode'] ?? 'prod') === 'demo' ? 'demo' : 'prod';
        if ($username === '' || $password === '') {
            throw new ConnectorException('Servono nome utente e password.');
        }
        $this->requestToken($username, $password, $mode);
        $summary = $mode === 'demo' ? 'ambiente demo' : 'invio reale allo SdI';
        $this->saveVerified('api_key', ['api_key' => $password], ['username' => $username, 'mode' => $mode], $summary);

        return $summary;
    }

    public function sendInvoice(Invoice $invoice, string $xml, string $fileName, array $customer): array
    {
        $response = Http::request('POST', $this->wsBase() . '/services/invoice/upload', [
            'Authorization: Bearer ' . $this->token(),
            'Content-Type: application/json;charset=UTF-8',
        ], json_encode(['dataFile' => base64_encode($xml), 'credential' => '', 'domain' => '']), 30);
        $result = $response['json'] ?? [];
        if ($response['status'] !== 200 || !empty($result['errorCode']) && $result['errorCode'] !== '0000') {
            throw new InvoicingException('Aruba: ' . ($result['errorDescription'] ?? 'invio non riuscito (codice ' . $response['status'] . ')'));
        }

        return ['reference' => (string) ($result['uploadFileName'] ?? $fileName), 'status' => 'inviata'];
    }

    private function token(): string
    {
        $meta = $this->meta();

        return $this->requestToken((string) ($meta['username'] ?? ''), (string) $this->apiKey(), (string) ($meta['mode'] ?? 'prod'));
    }

    private function requestToken(string $username, string $password, string $mode): string
    {
        $auth = $mode === 'demo' ? 'https://demoauth.fatturazioneelettronica.aruba.it' : 'https://auth.fatturazioneelettronica.aruba.it';
        $response = Http::request('POST', $auth . '/auth/signin', ['Content-Type: application/x-www-form-urlencoded;charset=UTF-8'], http_build_query([
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ]));
        if ($response['status'] !== 200 || !isset($response['json']['access_token'])) {
            throw new ConnectorException('Aruba non accetta queste credenziali (o l\'accesso alle API non e\' attivo).');
        }

        return (string) $response['json']['access_token'];
    }

    private function wsBase(): string
    {
        return ($this->meta()['mode'] ?? 'prod') === 'demo' ? 'https://demows.fatturazioneelettronica.aruba.it' : 'https://ws.fatturazioneelettronica.aruba.it';
    }
}
