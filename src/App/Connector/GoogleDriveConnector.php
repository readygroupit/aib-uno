<?php

declare(strict_types=1);

namespace App\Connector;

use App\Documents\DocumentStoreInterface;
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
final class GoogleDriveConnector extends AbstractOAuthConnector implements RedirectingConnectorInterface, MediaSourceInterface, DocumentStoreInterface
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API = 'https://www.googleapis.com/drive/v3';
    // Lettura e scrittura: dal gestionale si creano cartelle e si caricano
    // file (Documenti). Un collegamento fatto prima in sola lettura continua a
    // leggere; per scrivere va ricollegato (canWrite()).
    private const SCOPE = 'https://www.googleapis.com/auth/drive';
    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';
    private const ITEM_FIELDS = 'id,name,mimeType,parents,size,modifiedTime,webViewLink,md5Checksum,trashed';
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
            'intro' => 'I file restano sul Drive dell\'agenzia: il gestionale ne tiene le informazioni, crea le cartelle di clienti e progetti e ci carica i file. Foto e video li fa anche descrivere dall\'IA e li rende ricercabili a parole ("foto per una comunione senza persone"). Non cancella mai niente per sempre: al massimo mette nel cestino del Drive.',
            'steps' => [
                'Su console.cloud.google.com, nel progetto del Calendario (o in uno nuovo), in "API e servizi", "Libreria" attiva "Google Drive API".',
                'Se hai gia\' collegato il Calendario usa lo stesso ID client e salta al passo 4. Altrimenti in "Google Auth Platform" (prima "Schermata consenso OAuth") compila nome app ed email; con un Gmail normale, in "Pubblico" premi "Pubblica app" (con Google Workspace scegli invece "Interno").',
                'In "Client" (o "Credenziali", "ID client OAuth") crea un client "Applicazione web" con l\'URI di reindirizzamento autorizzato ' . $redirect . ' e copia ID client e Client secret.',
                'Sul Drive apri la cartella che contiene l\'archivio (ad esempio "Clienti", con una sottocartella per cliente) e copia l\'indirizzo dalla barra del browser.',
                'Incolla ID client, segreto e indirizzo della cartella e premi "Verifica e collega": si apre Google, entra con l\'account che vede la cartella e consenti l\'accesso al Drive (se Google avvisa che l\'app non e\' verificata: "Avanzate", "Vai a ..."). Se era gia\' collegato in sola lettura, ricollegalo: serve per creare cartelle e caricare file.',
            ],
            'fields' => [
                ['key' => 'client_id', 'label' => 'ID client', 'type' => 'text', 'placeholder' => '1234-abc.apps.googleusercontent.com'],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'placeholder' => 'GOCSPX-...'],
                ['key' => 'folder', 'label' => 'Cartella dell\'archivio', 'type' => 'text', 'placeholder' => 'https://drive.google.com/drive/folders/...', 'help' => 'Le sottocartelle con il nome di un cliente collegano i file a quel cliente.'],
            ],
            'about' => [
                'purpose' => 'Foto e video restano sul Drive, ma diventano un archivio in cui si cerca a parole ("foto di pizza senza persone per Rossano"), con il cliente gia\' riconosciuto dalla cartella.',
                'auto' => [
                    'Ogni ora legge i file nuovi, cambiati o cancellati della cartella (solo foto e video).',
                    'Collega i file al cliente quando una cartella del percorso si chiama come lui.',
                    'Ogni 10 minuti l\'IA descrive 15 file: cosa si vede, persone, ambiente, occasione, orientamento e, per i video, i momenti principali.',
                    'Ogni 15 minuti legge cartelle e file nuovi, rinominati, spostati o messi nel cestino (Documenti).',
                    'Crea le cartelle di un progetto nuovo (cliente, progetto, sottocartelle del processo) e carica i file scelti dal gestionale.',
                    'Non cancella mai nulla per sempre: quello che si elimina dal gestionale va nel cestino del Drive.',
                ],
                'manual' => [
                    'Organizzi le cartelle sul Drive (una per cliente).',
                    'Cerchi a parole nell\'archivio o in chat; correggi una descrizione o la fai rifare.',
                    'Se non vuoi aspettare: "Leggi i file nuovi" e "Descrivi ora".',
                ],
                'where' => 'Archivio foto e video (menu), e cercando in chat.',
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

        return 'ora autorizza l\'accesso al Drive su Google';
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
            ['client_id' => $meta['client_id'], 'client_secret' => $meta['client_secret'], 'folder_id' => $meta['folder_id'], 'folder_name' => $folder['name'] ?? null, 'redirect_uri' => $meta['redirect_uri'] ?? null, 'scope' => self::SCOPE],
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

    public function canWrite(): bool
    {
        return $this->isConnected() && ($this->meta()['scope'] ?? null) === self::SCOPE;
    }

    public function rootFolderId(): ?string
    {
        $id = (string) ($this->meta()['folder_id'] ?? '');

        return $id !== '' ? $id : null;
    }

    public function createFolder(string $name, string $parentId): array
    {
        $response = $this->write('POST', self::API . '/files?supportsAllDrives=true&fields=' . self::ITEM_FIELDS, [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);

        return self::item($response);
    }

    /**
     * Caricamento "a riprese" di Google: prima i dati del file, poi il
     * contenuto in un colpo solo (vale anche per i video grandi).
     */
    public function uploadFile(string $localPath, string $name, string $mime, string $parentId): array
    {
        $token = $this->writeToken();
        $curl = curl_init(self::UPLOAD_API . '/files?uploadType=resumable&supportsAllDrives=true&fields=' . self::ITEM_FIELDS);
        $headers = [];
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Content-Type: application/json; charset=UTF-8', 'X-Upload-Content-Type: ' . $mime, 'X-Upload-Content-Length: ' . filesize($localPath)],
            CURLOPT_POSTFIELDS => json_encode(['name' => $name, 'parents' => [$parentId]]),
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$headers) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);
        curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $location = $headers['location'] ?? null;
        if ($status !== 200 || $location === null) {
            throw new ConnectorException('Google Drive non ha accettato il caricamento (codice ' . $status . ').');
        }

        $handle = fopen($localPath, 'rb');
        $curl = curl_init($location);
        curl_setopt_array($curl, [
            CURLOPT_PUT => true,
            CURLOPT_INFILE => $handle,
            CURLOPT_INFILESIZE => filesize($localPath),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 1800,
            CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Content-Type: ' . $mime],
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        fclose($handle);
        $json = is_string($body) ? json_decode($body, true) : null;
        if (!in_array($status, [200, 201], true) || !is_array($json)) {
            throw new ConnectorException('Il file non e\' arrivato su Google Drive (codice ' . $status . ').');
        }

        return self::item($json);
    }

    public function rename(string $id, string $name): void
    {
        $this->write('PATCH', self::API . '/files/' . rawurlencode($id) . '?supportsAllDrives=true', ['name' => $name]);
    }

    public function trash(string $id): void
    {
        $this->write('PATCH', self::API . '/files/' . rawurlencode($id) . '?supportsAllDrives=true', ['trashed' => true]);
    }

    public function listChildren(string $folderId): iterable
    {
        $pageToken = null;
        do {
            $query = [
                'q' => "'{$folderId}' in parents and trashed = false",
                'fields' => 'nextPageToken,files(' . self::ITEM_FIELDS . ')',
                'pageSize' => 1000,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ];
            if ($pageToken !== null) {
                $query['pageToken'] = $pageToken;
            }
            $page = $this->get('/files?' . http_build_query($query));
            foreach ($page['files'] ?? [] as $file) {
                yield self::item($file);
            }
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken !== null);
    }

    public function startPageToken(): string
    {
        $response = $this->get('/changes/startPageToken?supportsAllDrives=true');

        return (string) ($response['startPageToken'] ?? '');
    }

    public function changes(string $pageToken): array
    {
        $items = [];
        $next = $pageToken;
        do {
            $page = $this->get('/changes?' . http_build_query([
                'pageToken' => $next,
                'fields' => 'nextPageToken,newStartPageToken,changes(fileId,removed,file(' . self::ITEM_FIELDS . '))',
                'pageSize' => 1000,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
                'includeRemoved' => 'true',
            ]));
            foreach ($page['changes'] ?? [] as $change) {
                $items[] = !empty($change['removed']) || !isset($change['file'])
                    ? ['id' => (string) $change['fileId'], 'removed' => true]
                    : self::item($change['file']) + ['removed' => false];
            }
            $token = $page['nextPageToken'] ?? null;
            $next = $token ?? (string) ($page['newStartPageToken'] ?? $next);
        } while ($token !== null);

        return ['items' => $items, 'next' => $next];
    }

    /** @return array<string,mixed> */
    private static function item(array $file): array
    {
        $mime = (string) ($file['mimeType'] ?? '');

        return [
            'id' => (string) ($file['id'] ?? ''),
            'name' => (string) ($file['name'] ?? ''),
            'mime' => $mime,
            'isFolder' => $mime === 'application/vnd.google-apps.folder',
            'parents' => array_map('strval', $file['parents'] ?? []),
            'size' => isset($file['size']) ? (int) $file['size'] : null,
            'webLink' => $file['webViewLink'] ?? null,
            'modifiedAt' => isset($file['modifiedTime']) ? date('Y-m-d H:i:s', strtotime((string) $file['modifiedTime'])) : null,
            'md5' => $file['md5Checksum'] ?? null,
            'trashed' => !empty($file['trashed']),
        ];
    }

    private function writeToken(): string
    {
        if (!$this->canWrite()) {
            throw new ConnectorException('Google Drive e\' collegato in sola lettura: ricollegalo dalla Configurazione per creare cartelle e caricare file.');
        }
        $token = $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Google Drive non e\' collegato o l\'accesso e\' scaduto: ricollegalo dalla Configurazione.');
        }

        return $token;
    }

    private function write(string $method, string $url, array $body): array
    {
        $response = Http::request($method, $url, ['Authorization: Bearer ' . $this->writeToken(), 'Content-Type: application/json; charset=UTF-8'], json_encode($body), 30);
        if (!in_array($response['status'], [200, 201], true)) {
            throw new ConnectorException('Google Drive ha risposto con il codice ' . $response['status'] . ($response['json']['error']['message'] ?? '' ? ': ' . $response['json']['error']['message'] : '') . '.');
        }

        return $response['json'] ?? [];
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
