<?php

declare(strict_types=1);

namespace App\Connector;

use App\Model\ConnectorCredential;

/**
 * Accesso a un foglio Google tramite "account di servizio" invece del
 * consenso OAuth utente: niente app da far approvare a Google ne' schermata
 * di consenso, l'operatore condivide il foglio con l'email dell'account di
 * servizio come farebbe con una persona. Il token (di breve durata) si
 * ottiene firmando un JWT con la chiave privata del file JSON - per
 * questo estende AbstractOAuthConnector (stessa logica "scaduto? rinnova"),
 * anche se non c'e' nessun refresh_token vero.
 */
final class GoogleSheetsConnector extends AbstractOAuthConnector
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    public function code(): string
    {
        return 'google_sheets';
    }

    public function label(): string
    {
        return 'Google Sheets';
    }

    public function test(): bool
    {
        $meta = $this->meta();
        $token = $this->accessToken();
        if ($token === null || !is_string($meta['spreadsheet_id'] ?? null)) {
            return false;
        }

        try {
            $this->fetchSpreadsheet($token, $meta['spreadsheet_id'], (string) ($meta['client_email'] ?? ''));

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        return [
            'intro' => 'Collega il foglio Google che usate come archivio. Si fa con un "account di servizio": un utente tecnico a cui darai accesso al foglio.',
            'steps' => [
                'Apri console.cloud.google.com, crea (o scegli) un progetto e attiva "Google Sheets API" dalla sezione "API e servizi".',
                'In "IAM e amministrazione", "Account di servizio", creane uno. Aprilo, vai su "Chiavi", "Aggiungi chiave", "Crea nuova chiave", formato JSON: si scarica un file.',
                'Apri il file JSON con un editor di testo e copia tutto il contenuto nel primo campo qui sotto.',
                'Apri il tuo foglio Google, premi "Condividi" e aggiungi come Editor l\'indirizzo che nel file JSON si chiama "client_email" (finisce con iam.gserviceaccount.com).',
                'Copia l\'indirizzo del foglio dalla barra del browser e incollalo nel secondo campo.',
            ],
            'fields' => [
                ['key' => 'service_account_json', 'label' => 'Contenuto del file JSON', 'type' => 'textarea', 'placeholder' => '{ "type": "service_account", ... }'],
                ['key' => 'spreadsheet', 'label' => 'Indirizzo del foglio', 'type' => 'text', 'placeholder' => 'https://docs.google.com/spreadsheets/d/...'],
            ],
            'note' => 'Qui si verifica solo l\'accesso al foglio e si legge il suo nome. La sincronizzazione con i contatti e le pratiche e\' il passo successivo.',
        ];
    }

    public function connect(array $input): string
    {
        $account = json_decode(trim($input['service_account_json'] ?? ''), true);
        if (!is_array($account) || ($account['type'] ?? null) !== 'service_account'
            || !is_string($account['client_email'] ?? null) || !is_string($account['private_key'] ?? null)) {
            throw new ConnectorException('Il contenuto incollato non e\' il file JSON di un account di servizio. Deve iniziare con { e contenere "client_email" e "private_key".');
        }

        $spreadsheetId = $this->spreadsheetId(trim($input['spreadsheet'] ?? ''));
        $token = $this->requestToken($account);
        $summary = $this->fetchSpreadsheet($token['access_token'], $spreadsheetId, $account['client_email']);

        $this->saveVerified(
            'oauth',
            [
                'access_token' => $token['access_token'],
                'token_expires_at' => date('Y-m-d H:i:s', time() + $token['expires_in'] - 60),
            ],
            [
                'spreadsheet_id' => $spreadsheetId,
                'client_email' => $account['client_email'],
                'service_account' => $account,
            ],
            $summary
        );

        return $summary;
    }

    protected function refreshAccessToken(ConnectorCredential $credential): ?string
    {
        $extra = json_decode((string) $credential->extraConfig, true);
        $account = is_array($extra) ? ($extra['service_account'] ?? null) : null;
        if (!is_array($account)) {
            return null;
        }

        try {
            $token = $this->requestToken($account);
        } catch (ConnectorException) {
            return null;
        }

        $this->credentials->update($credential->id, [
            'access_token' => $token['access_token'],
            'token_expires_at' => date('Y-m-d H:i:s', time() + $token['expires_in'] - 60),
        ]);

        return $token['access_token'];
    }

    private function spreadsheetId(string $input): string
    {
        if (preg_match('#/spreadsheets/d/([A-Za-z0-9_-]+)#', $input, $m)) {
            return $m[1];
        }
        if (preg_match('/^[A-Za-z0-9_-]{20,}$/', $input)) {
            return $input;
        }

        throw new ConnectorException('Non riesco a leggere l\'indirizzo del foglio. Incolla l\'indirizzo completo dalla barra del browser (contiene /spreadsheets/d/...).');
    }

    /** @return array{access_token: string, expires_in: int} */
    private function requestToken(array $account): array
    {
        $now = time();
        $segments = [
            self::base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url(json_encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        $signature = '';
        $key = openssl_pkey_get_private((string) $account['private_key']);
        if ($key === false || !openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new ConnectorException('La chiave privata nel file JSON non e\' valida: scarica di nuovo il file da Google Cloud e incollalo per intero.');
        }
        $segments[] = self::base64Url($signature);

        $response = Http::request(
            'POST',
            self::TOKEN_URL,
            ['Content-Type: application/x-www-form-urlencoded'],
            http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => implode('.', $segments)])
        );

        $token = $response['json']['access_token'] ?? null;
        if ($response['status'] !== 200 || !is_string($token)) {
            throw new ConnectorException('Google non accetta questo account di servizio. Verifica che la chiave non sia stata eliminata e che "Google Sheets API" sia attiva nel progetto.');
        }

        return ['access_token' => $token, 'expires_in' => (int) ($response['json']['expires_in'] ?? 3600)];
    }

    private function fetchSpreadsheet(string $token, string $spreadsheetId, string $clientEmail): string
    {
        $response = Http::request(
            'GET',
            "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}?fields=properties.title,sheets.properties.title",
            ["Authorization: Bearer {$token}"]
        );

        if ($response['status'] === 403) {
            throw new ConnectorException("Il foglio esiste ma non e' condiviso con l'account di servizio. Aprilo, premi \"Condividi\" e aggiungi come Editor: {$clientEmail}");
        }
        if ($response['status'] === 404) {
            throw new ConnectorException('Google non trova nessun foglio con questo indirizzo. Controlla di aver copiato l\'indirizzo giusto.');
        }
        if ($response['status'] !== 200 || !isset($response['json']['properties']['title'])) {
            throw new ConnectorException("Google ha risposto in modo inatteso (codice {$response['status']}). Riprova tra poco.");
        }

        $tabs = count($response['json']['sheets'] ?? []);

        return 'Foglio "' . $response['json']['properties']['title'] . '" ' . ($tabs === 1 ? '(1 scheda)' : "({$tabs} schede)");
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
