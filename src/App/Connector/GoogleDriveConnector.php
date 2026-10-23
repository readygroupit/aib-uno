<?php

declare(strict_types=1);

namespace App\Connector;

use App\Media\MediaSourceInterface;
use App\Model\ConnectorCredential;
use App\Model\MediaAsset;

/**
 * Google Drive come archivio di foto e video (sola lettura): Uno legge
 * l'albero della cartella d'archivio scelta (anche in un Drive condiviso),
 * scarica le immagini e i video non troppo grandi per farli descrivere
 * dall'IA, e per i video grandi usa l'anteprima di Google.
 *
 * OAuth come Google Calendar e con lo stesso callback: si puo' usare lo
 * stesso ID client. Lo "state" comincia con 'drive.' per distinguere i due
 * ritorni (vedi SetupController::googleCallbackAction()).
 */
final class GoogleDriveConnector extends AbstractOAuthConnector implements RedirectingConnectorInterface, MediaSourceInterface
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API = 'https://www.googleapis.com/drive/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/drive.readonly';
    public const STATE_PREFIX = 'drive.';
    private const FIELDS = 'nextPageToken,files(id,name,mimeType,size,modifiedTime,webViewLink,thumbnailLink,imageMediaMetadata(width,height),videoMediaMetadata(width,height,durationMillis))';

    private ?string $pendingRedirect = null;

    public function code(): string
    {
        return 'google_drive';
    }

    public function label(): string
    {
        return 'Google Drive';
    }

    public function sourceCode(): string
    {
        return 'google_drive';
    }

    public function isReady(): bool
    {
        return $this->isConnected();
    }

    public function test(): bool
    {
        try {
            $this->folderInfo();

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        $redirect = self::baseUrl() . GoogleCalendarConnector::CALLBACK_PATH;

        return [
            'intro' => 'Foto e video restano sul Drive dell\'agenzia: Uno li legge, li fa descrivere dall\'IA e li rende ricercabili a parole ("foto per una comunione senza persone"). Solo lettura: Uno non modifica ne\' cancella nulla.',
            'steps' => [
                'Su console.cloud.google.com, nello stesso progetto del Calendario, in "API e servizi", "Libreria" attiva "Google Drive API".',
                'Puoi usare lo stesso "ID client OAuth" del Calendario (con l\'URI di reindirizzamento ' . $redirect . ').',
                'Sul Drive apri la cartella che contiene l\'archivio (ad esempio "Clienti", con una sottocartella per cliente) e copia l\'indirizzo dalla barra del browser.',
                'Incolla ID client, segreto e indirizzo della cartella e premi "Verifica e collega": si apre Google, entra con l\'account dell\'agenzia e consenti la lettura del Drive.',
            ],
            'fields' => [
                ['key' => 'client_id', 'label' => 'ID client', 'type' => 'text', 'placeholder' => '1234-abc.apps.googleusercontent.com'],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'placeholder' => 'GOCSPX-...'],
                ['key' => 'folder', 'label' => 'Cartella dell\'archivio', 'type' => 'text', 'placeholder' => 'https://drive.google.com/drive/folders/...', 'help' => 'Le sottocartelle con il nome di un cliente collegano i file a quel cliente.'],
            ],
            'note' => 'I file nuovi si leggono ogni ora e si descrivono a gruppi; i video piu\' grandi di 300 MB si descrivono dall\'anteprima di Google.',
        ];
    }

    public function connect(array $input): string
    {
        $clientId = trim($input['client_id'] ?? '');
        $clientSecret = trim($input['client_secret'] ?? '');
        $folderId = self::folderIdFrom((string) ($input['folder'] ?? ''));
        if (!str_ends_with($clientId, '.apps.googleusercontent.com')) {
            throw new ConnectorException("L'ID client non sembra giusto: finisce con .apps.googleusercontent.com.");
        }
        if (strlen($clientSecret) < 10) {
            throw new ConnectorException('Manca il client secret.');
        }
        if ($folderId === null) {
            throw new ConnectorException('Incolla l\'indirizzo della cartella d\'archivio (drive.google.com/drive/folders/...).');
        }

        $state = self::STATE_PREFIX . bin2hex(random_bytes(16));
        $_SESSION['google_drive_oauth_state'] = $state;
        $this->savePending(['client_id' => $clientId, 'client_secret' => $clientSecret, 'folder_id' => $folderId, 'redirect_uri' => self::baseUrl() . GoogleCalendarConnector::CALLBACK_PATH]);

        $this->pendingRedirect = self::AUTH_URL . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => self::baseUrl() . GoogleCalendarConnector::CALLBACK_PATH,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'ora autorizza la lettura del Drive su Google';
    }

    public function redirectUrl(): ?string
    {
        return $this->pendingRedirect;
    }

    public function completeAuthorization(string $code, string $state): string
    {
        $expected = $_SESSION['google_drive_oauth_state'] ?? null;
        unset($_SESSION['google_drive_oauth_state']);
        if ($expected === null || !hash_equals($expected, $state)) {
            throw new ConnectorException('La risposta di Google non corrisponde a questa richiesta. Riprova il collegamento.');
        }
        $meta = $this->meta();
        if (!isset($meta['client_id'], $meta['client_secret'], $meta['folder_id'])) {
            throw new ConnectorException('Mancano i dati del collegamento: ricomincia dalla Configurazione.');
        }

        $response = Http::request('POST', self::TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'code' => $code,
            'client_id' => $meta['client_id'],
            'client_secret' => $meta['client_secret'],
            'redirect_uri' => $meta['redirect_uri'] ?? self::baseUrl() . GoogleCalendarConnector::CALLBACK_PATH,
            'grant_type' => 'authorization_code',
        ]));
        $token = $response['json'] ?? [];
        if ($response['status'] !== 200 || !isset($token['access_token'], $token['refresh_token'])) {
            throw new ConnectorException('Google non ha dato un accesso permanente al Drive (' . ($token['error_description'] ?? $token['error'] ?? 'codice ' . $response['status']) . ').');
        }

        $folder = $this->folderInfo((string) $token['access_token'], (string) $meta['folder_id']);
        $summary = 'cartella "' . ($folder['name'] ?? $meta['folder_id']) . '"';
        $this->saveVerified(
            'oauth',
            [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'],
                'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($token['expires_in'] ?? 3600) - 60),
            ],
            ['client_id' => $meta['client_id'], 'client_secret' => $meta['client_secret'], 'folder_id' => $meta['folder_id'], 'folder_name' => $folder['name'] ?? null, 'redirect_uri' => $meta['redirect_uri'] ?? null],
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

    /** L'albero della cartella d'archivio, cartella per cartella. */
    public function listFiles(): iterable
    {
        $root = (string) ($this->meta()['folder_id'] ?? '');
        if ($root === '') {
            return;
        }
        $queue = [[$root, '']];
        $seen = [];
        while ($queue !== []) {
            [$folderId, $path] = array_shift($queue);
            if (isset($seen[$folderId])) {
                continue;
            }
            $seen[$folderId] = true;
            $pageToken = null;
            do {
                $query = [
                    'q' => "'{$folderId}' in parents and trashed = false",
                    'fields' => self::FIELDS,
                    'pageSize' => 1000,
                    'supportsAllDrives' => 'true',
                    'includeItemsFromAllDrives' => 'true',
                ];
                if ($pageToken !== null) {
                    $query['pageToken'] = $pageToken;
                }
                $page = $this->get('/files?' . http_build_query($query));
                foreach ($page['files'] ?? [] as $file) {
                    $mime = (string) ($file['mimeType'] ?? '');
                    if ($mime === 'application/vnd.google-apps.folder') {
                        $queue[] = [(string) $file['id'], ltrim($path . '/' . $file['name'], '/')];
                        continue;
                    }
                    if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
                        continue;
                    }
                    $image = $file['imageMediaMetadata'] ?? [];
                    $video = $file['videoMediaMetadata'] ?? [];
                    yield [
                        'id' => (string) $file['id'],
                        'name' => (string) $file['name'],
                        'mime' => $mime,
                        'folder' => $path,
                        'webLink' => $file['webViewLink'] ?? null,
                        'size' => isset($file['size']) ? (int) $file['size'] : null,
                        'modifiedAt' => isset($file['modifiedTime']) ? date('Y-m-d H:i:s', strtotime((string) $file['modifiedTime'])) : null,
                        'width' => $image['width'] ?? $video['width'] ?? null,
                        'height' => $image['height'] ?? $video['height'] ?? null,
                        'duration' => isset($video['durationMillis']) ? (int) round($video['durationMillis'] / 1000) : null,
                    ];
                }
                $pageToken = $page['nextPageToken'] ?? null;
            } while ($pageToken !== null);
        }
    }

    public function download(MediaAsset $asset, string $target, int $maxBytes): bool
    {
        if ($asset->sizeBytes !== null && $asset->sizeBytes > $maxBytes) {
            return false;
        }
        $token = $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Google Drive non e\' collegato o l\'accesso e\' scaduto.');
        }
        $handle = fopen($target, 'wb');
        $curl = curl_init(self::API . '/files/' . rawurlencode((string) $asset->externalId) . '?alt=media&supportsAllDrives=true');
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}"],
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        fclose($handle);

        return $ok !== false && $status === 200 && filesize($target) > 0;
    }

    public function previewImage(MediaAsset $asset): ?string
    {
        $file = $this->get('/files/' . rawurlencode((string) $asset->externalId) . '?fields=thumbnailLink&supportsAllDrives=true');
        $link = $file['thumbnailLink'] ?? null;
        if ($link === null) {
            return null;
        }
        // L'anteprima di Google si puo' chiedere piu' grande cambiando la misura finale (=s220).
        $link = preg_replace('/=s\d+$/', '=s1280', (string) $link);
        $curl = curl_init($link);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->accessToken()]]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $status === 200 && is_string($body) && $body !== '' ? $body : null;
    }

    /** L'ID della cartella da un indirizzo del Drive (o l'ID stesso). */
    public static function folderIdFrom(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('#/folders/([A-Za-z0-9_-]{10,})#', $input, $m) === 1 || preg_match('#[?&]id=([A-Za-z0-9_-]{10,})#', $input, $m) === 1) {
            return $m[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{10,}$/', $input) === 1 ? $input : null;
    }

    private function folderInfo(?string $token = null, ?string $folderId = null): array
    {
        $token ??= $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Google Drive non e\' collegato.');
        }
        $id = rawurlencode($folderId ?? (string) ($this->meta()['folder_id'] ?? ''));
        $response = Http::request('GET', self::API . "/files/{$id}?fields=id,name,mimeType&supportsAllDrives=true", ["Authorization: Bearer {$token}"]);
        if ($response['status'] === 404) {
            throw new ConnectorException('Cartella non trovata: controlla l\'indirizzo e che l\'account abbia accesso.');
        }
        if ($response['status'] !== 200 || ($response['json']['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') {
            throw new ConnectorException('Google Drive non risponde come previsto, o l\'indirizzo non e\' una cartella.');
        }

        return $response['json'];
    }

    private function get(string $path): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Google Drive non e\' collegato o l\'accesso e\' scaduto: ricollegalo dalla Configurazione.');
        }
        $response = Http::request('GET', self::API . $path, ["Authorization: Bearer {$token}"], null, 30);
        if ($response['status'] !== 200) {
            throw new ConnectorException('Google Drive ha risposto con il codice ' . $response['status'] . '.');
        }

        return $response['json'] ?? [];
    }

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

    private static function baseUrl(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
