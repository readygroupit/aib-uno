<?php

declare(strict_types=1);

namespace App\Connector;

/** WhatsApp Business Cloud API di Meta: token permanente + id del numero. */
final class WhatsAppConnector extends AbstractApiKeyConnector
{
    private const GRAPH = 'https://graph.facebook.com/v21.0';

    public function code(): string
    {
        return 'whatsapp';
    }

    public function label(): string
    {
        return 'WhatsApp';
    }

    public function test(): bool
    {
        $key = $this->apiKey();
        $phoneId = $this->meta()['phone_number_id'] ?? null;
        if ($key === null || !is_string($phoneId)) {
            return false;
        }

        try {
            $this->fetchNumber($key, $phoneId);

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        return [
            'intro' => 'Collega il numero WhatsApp Business dell\'azienda tramite l\'API ufficiale di Meta.',
            'steps' => [
                'Su developers.facebook.com crea (o apri) un\'app di tipo "Business" e aggiungi il prodotto "WhatsApp".',
                'Nella sezione "Configurazione API" trovi l\'"ID numero di telefono": copialo nel secondo campo qui sotto.',
                'Per il token: in Business Manager apri Impostazioni, Utenti di sistema, crea un utente di sistema, assegnagli l\'app e genera un token con il permesso "whatsapp_business_messaging". Quello provvisorio della pagina API dura solo 24 ore.',
            ],
            'fields' => [
                ['key' => 'access_token', 'label' => 'Token di accesso permanente', 'type' => 'password', 'placeholder' => 'Incolla qui il token'],
                ['key' => 'phone_number_id', 'label' => 'ID numero di telefono', 'type' => 'text', 'placeholder' => 'Solo cifre, es. 109876543210987'],
            ],
            'note' => 'Qui si verifica solo che token e numero siano validi. L\'invio vero dei messaggi dalle comunicazioni e\' il passo successivo, e i primi contatti verso un cliente richiedono comunque un modello approvato da Meta.',
        ];
    }

    public function connect(array $input): string
    {
        $token = trim($input['access_token'] ?? '');
        $phoneId = trim($input['phone_number_id'] ?? '');
        if ($token === '' || $phoneId === '') {
            throw new ConnectorException('Servono sia il token di accesso sia l\'ID del numero di telefono.');
        }
        if (!ctype_digit($phoneId)) {
            throw new ConnectorException('L\'ID del numero di telefono contiene solo cifre: e\' diverso dal numero vero e proprio.');
        }

        $summary = $this->fetchNumber($token, $phoneId);
        $this->saveVerified('api_key', ['api_key' => $token], ['phone_number_id' => $phoneId], $summary);

        return $summary;
    }

    private function fetchNumber(string $token, string $phoneId): string
    {
        $response = Http::request('GET', self::GRAPH . "/{$phoneId}?fields=display_phone_number,verified_name", ["Authorization: Bearer {$token}"]);

        if ($response['status'] === 401 || ($response['json']['error']['code'] ?? null) === 190) {
            throw new ConnectorException('Meta non accetta questo token: e\' scaduto o non valido. Genera un token permanente da un utente di sistema.');
        }
        if ($response['status'] !== 200 || !isset($response['json']['display_phone_number'])) {
            throw new ConnectorException('Meta non trova questo numero con il token indicato. Controlla l\'ID del numero e che l\'app abbia accesso a quel numero.');
        }

        $name = $response['json']['verified_name'] ?? null;

        return 'Numero ' . $response['json']['display_phone_number'] . ($name ? " ({$name})" : '');
    }
}
