<?php

declare(strict_types=1);

namespace App\Connector;

use App\Invoicing\InvoiceProviderInterface;
use App\Invoicing\InvoicingException;
use App\Model\Invoice;

/**
 * Fatture in Cloud (API v2): la fattura si crea anche li' (cosi' c'e' il
 * PDF e la contabilita') e si manda allo SdI da li'. Si usa il "token
 * personale" generato nelle impostazioni dell'account e l'ID azienda.
 */
final class FattureInCloudConnector extends AbstractApiKeyConnector implements InvoiceProviderInterface
{
    private const API = 'https://api-v2.fattureincloud.it';
    private const PAYMENT_CODES = ['bonifico' => 'MP05', 'carta' => 'MP08', 'addebito SEPA' => 'MP19', 'PayPal' => 'MP08', 'contanti' => 'MP01'];

    public function code(): string
    {
        return 'fatture_in_cloud';
    }

    public function label(): string
    {
        return 'Fatture in Cloud';
    }

    public function test(): bool
    {
        try {
            $this->companyName((string) $this->apiKey(), (int) ($this->meta()['company_id'] ?? 0));

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        return [
            'intro' => 'Le fatture emesse qui vengono create anche su Fatture in Cloud e mandate allo SdI da li\'. Il PDF per il cliente lo fa Fatture in Cloud.',
            'steps' => [
                'Accedi a Fatture in Cloud, apri "Impostazioni", "Applicazioni collegate" (o "Sviluppatori") e crea un "Token personale" con i permessi su documenti emessi e fatturazione elettronica.',
                'Copia il token nel primo campo.',
                'L\'ID azienda e\' il numero nell\'indirizzo quando sei dentro l\'azienda (secure.fattureincloud.it/c/<ID>/...).',
            ],
            'fields' => [
                ['key' => 'api_key', 'label' => 'Token personale', 'type' => 'password', 'placeholder' => 'a/...'],
                ['key' => 'company_id', 'label' => 'ID azienda', 'type' => 'text', 'placeholder' => '123456'],
            ],
            'note' => 'Si verifica che il token veda l\'azienda indicata.',
        ];
    }

    public function connect(array $input): string
    {
        $token = trim($input['api_key'] ?? '');
        $companyId = (int) preg_replace('/\D/', '', (string) ($input['company_id'] ?? ''));
        if ($token === '' || $companyId === 0) {
            throw new ConnectorException('Servono token personale e ID azienda.');
        }
        $name = $this->companyName($token, $companyId);
        $summary = "azienda \u{ab}{$name}\u{bb}";
        $this->saveVerified('api_key', ['api_key' => $token], ['company_id' => $companyId], $summary);

        return $summary;
    }

    public function sendInvoice(Invoice $invoice, string $xml, string $fileName, array $customer): array
    {
        $companyId = (int) ($this->meta()['company_id'] ?? 0);
        $rate = (float) ($invoice->taxRatePercent ?? 22);
        $total = (float) $invoice->total;

        $created = $this->call('POST', "/c/{$companyId}/issued_documents", ['data' => [
            'type' => 'invoice',
            'entity' => array_filter([
                'name' => $customer['name'],
                'vat_number' => $customer['vat'] ?? null,
                'tax_code' => $customer['taxCode'] ?? null,
                'address_street' => $customer['address'] ?? null,
                'address_postal_code' => $customer['postalCode'] ?? null,
                'address_city' => $customer['city'] ?? null,
                'address_province' => $customer['province'] ?? null,
                'country' => 'Italia',
                'ei_code' => $customer['sdiCode'] ?: '0000000',
                'certified_email' => $customer['pec'] ?? null,
            ]),
            'date' => $invoice->issueDate,
            'number' => (int) $invoice->numberSeq,
            'items_list' => [[
                'name' => mb_substr((string) ($invoice->description ?: 'Servizi'), 0, 200),
                'net_price' => (float) $invoice->subtotal,
                'qty' => 1,
                'vat' => ['id' => $this->vatId($companyId, $rate)],
            ]],
            'payments_list' => [[
                'amount' => $total,
                'due_date' => $invoice->dueDate ?: $invoice->issueDate,
                'status' => $invoice->stage === 'pagata' ? 'paid' : 'not_paid',
            ]],
            'e_invoice' => true,
            'ei_data' => ['payment_method' => self::PAYMENT_CODES[$invoice->paymentMethod] ?? 'MP05'],
        ]]);
        $documentId = $created['data']['id'] ?? null;
        if ($documentId === null) {
            throw new InvoicingException('Fatture in Cloud non ha creato la fattura.');
        }

        $this->call('POST', "/c/{$companyId}/issued_documents/{$documentId}/e_invoice/send", ['data' => new \stdClass()]);

        return ['reference' => 'fic-' . $documentId, 'status' => 'inviata'];
    }

    private function vatId(int $companyId, float $rate): int
    {
        $types = $this->call('GET', "/c/{$companyId}/info/vat_types");
        foreach ($types['data'] ?? [] as $type) {
            if (abs((float) ($type['value'] ?? -1) - $rate) < 0.01 && empty($type['is_disabled'])) {
                return (int) $type['id'];
            }
        }
        throw new InvoicingException("Su Fatture in Cloud non c'e' un'aliquota IVA del {$rate}%.");
    }

    private function companyName(string $token, int $companyId): string
    {
        $response = Http::request('GET', self::API . '/user/companies', ["Authorization: Bearer {$token}"]);
        if ($response['status'] === 401) {
            throw new ConnectorException('Fatture in Cloud non riconosce il token.');
        }
        foreach ($response['json']['data']['companies'] ?? [] as $company) {
            if ((int) ($company['id'] ?? 0) === $companyId) {
                return (string) ($company['name'] ?? $companyId);
            }
        }
        throw new ConnectorException('Il token funziona ma non vede l\'azienda con questo ID.');
    }

    private function call(string $method, string $path, ?array $body = null): array
    {
        $response = Http::request($method, self::API . $path, [
            'Authorization: Bearer ' . $this->apiKey(),
            'Content-Type: application/json',
        ], $body !== null ? json_encode($body) : null, 25);
        if ($response['status'] >= 300) {
            $detail = $response['json']['error']['message'] ?? ($response['json']['error']['validation_result'] ?? null);
            throw new InvoicingException('Fatture in Cloud: ' . (is_string($detail) ? $detail : 'errore ' . $response['status']));
        }

        return $response['json'] ?? [];
    }
}
