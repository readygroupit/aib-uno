<?php

declare(strict_types=1);

namespace App\Connector;

use App\Model\ConnectorCredential;

/**
 * Google Calendar con un account aziendale (OAuth "web": si autorizza una
 * volta dal browser, poi il refresh token tiene vivo l'accesso). Gli
 * appuntamenti diventano eventi del calendario scelto, con i colleghi
 * invitati (ricevono l'evento nel loro calendario) e, per le
 * videochiamate, il link Meet creato da Google.
 *
 * Due passi, perche' OAuth passa dal browser:
 *  1. connect(): si salvano ID e segreto del client (non ancora collegato)
 *     e si prepara l'indirizzo di autorizzazione di Google (redirectUrl());
 *  2. completeAuthorization(): Google rimanda al callback con un codice, lo
 *     si scambia con i token e si verifica leggendo il calendario.
 */
final class GoogleCalendarConnector extends AbstractOAuthConnector implements RedirectingConnectorInterface
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API = 'https://www.googleapis.com/calendar/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/calendar';
    public const CALLBACK_PATH = '/configurazione/google/callback';

    private ?string $pendingRedirect = null;

    public function code(): string
    {
        return 'google_calendar';
    }

    public function label(): string
    {
        return 'Google Calendar';
    }

    public function test(): bool
    {
        try {
            $this->calendarInfo();

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        $redirect = self::baseUrl() . self::CALLBACK_PATH;

        return [
            'intro' => 'Gli appuntamenti finiscono sul Google Calendar dell\'agenzia, i colleghi ricevono l\'invito e le videochiamate hanno il link Meet. Si collega una volta, con l\'account Google aziendale.',
            'steps' => [
                'Apri console.cloud.google.com, crea (o scegli) un progetto e in "API e servizi", "Libreria" attiva "Google Calendar API".',
                'In "Schermata consenso OAuth" scegli "Interno" (solo per gli account della vostra azienda) e compila nome app ed email.',
                'In "Credenziali", "Crea credenziali", "ID client OAuth", tipo "Applicazione web". In "URI di reindirizzamento autorizzati" aggiungi esattamente: ' . $redirect,
                'Copia "ID client" e "Client secret" nei campi qui sotto e premi "Verifica e collega": si apre Google, entra con l\'account dell\'agenzia e consenti l\'accesso al calendario.',
            ],
            'fields' => [
                ['key' => 'client_id', 'label' => 'ID client', 'type' => 'text', 'placeholder' => '1234-abc.apps.googleusercontent.com'],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'placeholder' => 'GOCSPX-...'],
                ['key' => 'calendar_id', 'label' => 'Calendario (facoltativo)', 'type' => 'text', 'placeholder' => 'vuoto = il calendario principale dell\'account', 'help' => 'Per un calendario condiviso "Agenzia": impostazioni del calendario su Google, "ID calendario".'],
            ],
            'note' => 'Si sincronizza ogni pochi minuti nei due sensi: un evento creato su Google compare anche qui.',
        ];
    }

    public function connect(array $input): string
    {
        $clientId = trim($input['client_id'] ?? '');
        $clientSecret = trim($input['client_secret'] ?? '');
        if (!str_ends_with($clientId, '.apps.googleusercontent.com')) {
            throw new ConnectorException("L'ID client non sembra giusto: finisce con .apps.googleusercontent.com.");
        }
        if (strlen($clientSecret) < 10) {
            throw new ConnectorException('Manca il client secret.');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['google_calendar_oauth_state'] = $state;

        $this->savePending([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'calendar_id' => trim($input['calendar_id'] ?? '') ?: 'primary',
            'redirect_uri' => self::baseUrl() . self::CALLBACK_PATH,
        ]);

        $this->pendingRedirect = self::AUTH_URL . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::baseUrl() . self::CALLBACK_PATH,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            // 'consent' fa tornare sempre il refresh token, anche ricollegando.
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'ora autorizza l\'accesso su Google';
    }

    public function redirectUrl(): ?string
    {
        return $this->pendingRedirect;
    }

    /** Ritorno da Google: codice -> token, poi la verifica sul calendario. */
    public function completeAuthorization(string $code, string $state): string
    {
        $expected = $_SESSION['google_calendar_oauth_state'] ?? null;
        unset($_SESSION['google_calendar_oauth_state']);
        if ($expected === null || !hash_equals($expected, $state)) {
            throw new ConnectorException('La risposta di Google non corrisponde a questa richiesta. Riprova il collegamento.');
        }

        $meta = $this->meta();
        if (!isset($meta['client_id'], $meta['client_secret'])) {
            throw new ConnectorException('Mancano ID client e segreto: ricomincia dalla Configurazione.');
        }

        $response = Http::request('POST', self::TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'code' => $code,
            'client_id' => $meta['client_id'],
            'client_secret' => $meta['client_secret'],
            'redirect_uri' => $meta['redirect_uri'] ?? self::baseUrl() . self::CALLBACK_PATH,
            'grant_type' => 'authorization_code',
        ]));
        $token = $response['json'] ?? [];
        if ($response['status'] !== 200 || !isset($token['access_token'])) {
            throw new ConnectorException('Google non ha dato l\'accesso (' . ($token['error_description'] ?? $token['error'] ?? 'codice ' . $response['status']) . ').');
        }
        if (!isset($token['refresh_token'])) {
            throw new ConnectorException('Google non ha dato un accesso permanente. Togli l\'app dalle autorizzazioni dell\'account Google e ricollega.');
        }

        $calendar = $this->calendarInfo((string) $token['access_token'], (string) ($meta['calendar_id'] ?? 'primary'));
        $summary = 'calendario "' . ($calendar['summary'] ?? $meta['calendar_id']) . '"';

        $this->saveVerified(
            'oauth',
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
                'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600) - 60),
            ],
            [
                'client_id' => $meta['client_id'],
                'client_secret' => $meta['client_secret'],
                'calendar_id' => $meta['calendar_id'] ?? 'primary',
                'redirect_uri' => $meta['redirect_uri'] ?? null,
                'time_zone' => $calendar['timeZone'] ?? 'Europe/Rome',
            ],
            $summary
        );

        return $summary;
    }

    protected function refreshAccessToken(ConnectorCredential $credential): ?string
    {
        $meta = $this->meta();
        if ($credential->refreshToken === null || !isset($meta['client_id'], $meta['client_secret'])) {
            return null;
        }

        $response = Http::request('POST', self::TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'client_id' => $meta['client_id'],
            'client_secret' => $meta['client_secret'],
            'refresh_token' => $credential->refreshToken,
            'grant_type' => 'refresh_token',
        ]));
        $token = $response['json'] ?? [];
        if ($response['status'] !== 200 || !isset($token['access_token'])) {
            return null;
        }

        $this->credentials->update($credential->id, [
            'access_token' => $token['access_token'],
            'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600) - 60),
        ]);

        return (string) $token['access_token'];
    }

    public function timeZone(): string
    {
        return (string) ($this->meta()['time_zone'] ?? 'Europe/Rome');
    }

    /**
     * Crea o aggiorna un evento. Con 'conferenceData.createRequest' Google
     * crea il link Meet (serve conferenceDataVersion=1).
     *
     * @return array evento come lo restituisce Google
     */
    public function saveEvent(array $event, ?string $eventId): array
    {
        $calendar = rawurlencode($this->calendarId());
        $query = 'conferenceDataVersion=1&sendUpdates=all';
        [$method, $url] = $eventId === null
            ? ['POST', self::API . "/calendars/{$calendar}/events?{$query}"]
            : ['PATCH', self::API . "/calendars/{$calendar}/events/" . rawurlencode($eventId) . "?{$query}"];

        $response = $this->call($method, $url, $event);
        // Evento cancellato su Google nel frattempo: lo si ricrea.
        if ($eventId !== null && in_array($response['status'], [404, 410], true)) {
            return $this->saveEvent($event, null);
        }
        if ($response['status'] >= 300 || !isset($response['json']['id'])) {
            throw new ConnectorException('Google Calendar non ha salvato l\'evento (' . ($response['json']['error']['message'] ?? 'codice ' . $response['status']) . ').');
        }

        return $response['json'];
    }

    public function deleteEvent(string $eventId): void
    {
        $calendar = rawurlencode($this->calendarId());
        $response = $this->call('DELETE', self::API . "/calendars/{$calendar}/events/" . rawurlencode($eventId) . '?sendUpdates=all');
        if (!in_array($response['status'], [200, 204, 404, 410], true)) {
            throw new ConnectorException('Google Calendar non ha cancellato l\'evento (codice ' . $response['status'] . ').');
        }
    }

    /**
     * Eventi cambiati dall'ultima lettura (syncToken), oppure dal mese scorso
     * la prima volta. Se Google dice che il token e' scaduto (410), si
     * riparte da capo.
     *
     * @return array{events: list<array>, syncToken: ?string}
     */
    public function changedEvents(?string $syncToken): array
    {
        $calendar = rawurlencode($this->calendarId());
        $events = [];
        $pageToken = null;
        do {
            $params = ['maxResults' => 250, 'singleEvents' => 'true', 'showDeleted' => 'true'];
            if ($syncToken !== null) {
                $params['syncToken'] = $syncToken;
            } else {
                $params['timeMin'] = date('c', strtotime('-30 days'));
            }
            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }

            $response = $this->call('GET', self::API . "/calendars/{$calendar}/events?" . http_build_query($params));
            if ($response['status'] === 410 && $syncToken !== null) {
                return $this->changedEvents(null);
            }
            if ($response['status'] !== 200) {
                throw new ConnectorException('Google Calendar non ha restituito gli eventi (codice ' . $response['status'] . ').');
            }

            array_push($events, ...($response['json']['items'] ?? []));
            $pageToken = $response['json']['nextPageToken'] ?? null;
            $nextSync = $response['json']['nextSyncToken'] ?? null;
        } while ($pageToken !== null);

        return ['events' => $events, 'syncToken' => $nextSync ?? $syncToken];
    }

    public function syncState(): array
    {
        $meta = $this->meta();

        return ['syncToken' => $meta['sync_token'] ?? null, 'lastSyncAt' => $meta['last_sync_at'] ?? null, 'lastError' => $meta['last_error'] ?? null];
    }

    public function saveSyncState(?string $syncToken, ?string $error): void
    {
        $credential = $this->credential();
        if ($credential === null) {
            return;
        }
        $meta = $this->meta();
        $meta['sync_token'] = $syncToken ?? ($meta['sync_token'] ?? null);
        $meta['last_sync_at'] = date('Y-m-d H:i:s');
        $meta['last_error'] = $error;
        $this->credentials->update($credential->id, ['extra_config' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
    }

    private function calendarId(): string
    {
        return (string) ($this->meta()['calendar_id'] ?? 'primary');
    }

    /** @return array{summary?: string, timeZone?: string} */
    private function calendarInfo(?string $token = null, ?string $calendarId = null): array
    {
        $token ??= $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Google Calendar non e\' collegato.');
        }
        $id = rawurlencode($calendarId ?? $this->calendarId());
        $response = Http::request('GET', self::API . "/calendars/{$id}", ["Authorization: Bearer {$token}"]);
        if ($response['status'] === 404) {
            throw new ConnectorException('Calendario non trovato: controlla l\'ID calendario o lascialo vuoto per quello principale.');
        }
        if ($response['status'] !== 200) {
            throw new ConnectorException('Google Calendar non risponde come previsto (codice ' . $response['status'] . ').');
        }

        return $response['json'] ?? [];
    }

    private function call(string $method, string $url, ?array $body = null): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Google Calendar non e\' collegato o l\'accesso e\' scaduto: ricollegalo dalla Configurazione.');
        }

        return Http::request(
            $method,
            $url,
            ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
            $body !== null ? json_encode($body, JSON_UNESCAPED_UNICODE) : null,
            20
        );
    }

    /** Dati di client e calendario salvati prima dell'autorizzazione: il connettore non risulta ancora collegato. */
    private function savePending(array $extra): void
    {
        $data = [
            'connector_code' => $this->code(),
            'auth_type' => 'oauth',
            'extra_config' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'api_key' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => 1,
        ];
        $existing = $this->credentials->findByConnectorCode($this->code());
        $existing === null ? $this->credentials->insert($data) : $this->credentials->update((int) $existing->id, $data);
    }

    /** Indirizzo da cui si sta usando l'app: il callback di Google deve tornare qui. */
    private static function baseUrl(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
