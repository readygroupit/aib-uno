<?php

declare(strict_types=1);

namespace App\Connector;

use App\Media\MediaSourceInterface;
use App\Model\ConnectorCredential;
use App\Model\MediaAsset;

/**
 * pCloud come archivio di foto e video (sola lettura), come Google Drive:
 * Uno legge l'albero della cartella d'archivio, scarica i file non troppo
 * grandi per farli descrivere dall'IA e per gli altri usa l'anteprima di
 * pCloud. Una sottocartella con il nome di un cliente collega i suoi file.
 *
 * OAuth di pCloud: si crea un'app su docs.pcloud.com ("My applications"),
 * il ritorno arriva a /configurazione/pcloud/callback con il codice e
 * l'host del conto (api.pcloud.com negli USA, eapi.pcloud.com in Europa).
 * Il token di pCloud non scade: niente rinnovo.
 */
final class PCloudConnector extends AbstractOAuthConnector implements RedirectingConnectorInterface, MediaSourceInterface
{
    private const AUTH_URL = 'https://my.pcloud.com/oauth2/authorize';
    public const CALLBACK_PATH = '/configurazione/pcloud/callback';
    private const HOSTS = ['api.pcloud.com', 'eapi.pcloud.com'];
    private const EXTENSIONS = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'heic' => 'image/heic', 'gif' => 'image/gif',
        'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'm4v' => 'video/mp4', 'webm' => 'video/webm',
    ];

    private ?string $pendingRedirect = null;

    public function code(): string
    {
        return 'pcloud';
    }

    public function label(): string
    {
        return 'pCloud';
    }

    public function sourceCode(): string
    {
        return 'pcloud';
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
        return [
            'intro' => 'Foto e video restano su pCloud: Uno li legge, li fa descrivere dall\'IA e li rende ricercabili a parole, accanto a quelli di Google Drive. Solo lettura: Uno non modifica ne\' cancella nulla.',
            'steps' => [
                'Entra su docs.pcloud.com con l\'account pCloud dell\'agenzia (va bene sia un conto europeo sia americano), apri "My applications" e crea un\'applicazione (nome a piacere, es. "Gestionale").',
                'Nell\'applicazione, in "Redirect URIs", aggiungi ' . self::baseUrl() . self::CALLBACK_PATH . ' e dai il permesso di lettura dei file.',
                'Su my.pcloud.com apri la cartella che contiene l\'archivio (ad esempio "Clienti", con una sottocartella per cliente) e copia l\'indirizzo dalla barra del browser.',
                'Incolla Client ID, Client secret e indirizzo della cartella e premi "Verifica e collega": si apre pCloud, entra e consenti l\'accesso.',
            ],
            'fields' => [
                ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'placeholder' => 'aBcD1234...'],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password'],
                ['key' => 'folder', 'label' => 'Cartella dell\'archivio', 'type' => 'text', 'placeholder' => 'https://my.pcloud.com/#page=filemanager&folder=123456', 'help' => 'L\'indirizzo della cartella, oppure il percorso (es. /Clienti). Le sottocartelle con il nome di un cliente collegano i file a quel cliente.'],
            ],
            'about' => [
                'purpose' => 'Come Google Drive, per foto e video che stanno su pCloud: finiscono nello stesso archivio e nella stessa ricerca a parole.',
                'auto' => [
                    'Ogni ora legge i file nuovi, cambiati o cancellati della cartella (solo foto e video).',
                    'Collega i file al cliente quando una cartella del percorso si chiama come lui.',
                    'Ogni 10 minuti l\'IA descrive 15 file, come per il Drive.',
                    'Non modifica, non sposta e non cancella nulla su pCloud.',
                ],
                'manual' => [
                    'Organizzi le cartelle su pCloud (una per cliente).',
                    'Cerchi a parole nell\'archivio o in chat; "Apri" porta alla cartella del file su pCloud.',
                    'Se non vuoi aspettare: "Leggi i file nuovi" e "Descrivi ora".',
                ],
                'where' => 'Archivio foto e video (menu), e cercando in chat.',
            ],
            'note' => 'I file nuovi si leggono ogni ora e si descrivono a gruppi; i video piu\' grandi di 300 MB si descrivono dall\'anteprima di pCloud.',
        ];
    }

    public function connect(array $input): string
    {
        $clientId = trim($input['client_id'] ?? '');
        $clientSecret = trim($input['client_secret'] ?? '');
        $folder = self::folderFrom((string) ($input['folder'] ?? ''));
        if (preg_match('/^[A-Za-z0-9]{6,}$/', $clientId) !== 1) {
            throw new ConnectorException('Il Client ID non sembra giusto: copialo dalla tua applicazione su docs.pcloud.com.');
        }
        if (strlen($clientSecret) < 10) {
            throw new ConnectorException('Manca il client secret.');
        }
        if ($folder === null) {
            throw new ConnectorException('Incolla l\'indirizzo della cartella d\'archivio (my.pcloud.com/#page=filemanager&folder=...) o il suo percorso (/Clienti).');
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION['pcloud_oauth_state'] = $state;
        $this->savePending(['client_id' => $clientId, 'client_secret' => $clientSecret, 'folder' => $folder]);

        $this->pendingRedirect = self::AUTH_URL . '?' . http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'redirect_uri' => self::baseUrl() . self::CALLBACK_PATH,
            'state' => $state,
        ]);

        return 'ora autorizza la lettura di pCloud';
    }

    public function redirectUrl(): ?string
    {
        return $this->pendingRedirect;
    }

    /** @param string $hostname l'host del conto che pCloud manda con il codice */
    public function completeAuthorization(string $code, string $state, string $hostname): string
    {
        $expected = $_SESSION['pcloud_oauth_state'] ?? null;
        unset($_SESSION['pcloud_oauth_state']);
        if ($expected === null || !hash_equals($expected, $state)) {
            throw new ConnectorException('La risposta di pCloud non corrisponde a questa richiesta. Riprova il collegamento.');
        }
        $meta = $this->meta();
        if (!isset($meta['client_id'], $meta['client_secret'], $meta['folder'])) {
            throw new ConnectorException('Mancano i dati del collegamento: ricomincia dalla Configurazione.');
        }
        // Solo i due host veri di pCloud: il token non va mandato altrove.
        $host = in_array($hostname, self::HOSTS, true) ? $hostname : self::HOSTS[0];

        $response = Http::request('GET', "https://{$host}/oauth2_token?" . http_build_query([
            'client_id' => $meta['client_id'],
            'client_secret' => $meta['client_secret'],
            'code' => $code,
        ]));
        $token = $response['json'] ?? [];
        if (($token['result'] ?? null) !== 0 || !isset($token['access_token'])) {
            throw new ConnectorException('pCloud non ha dato l\'accesso (' . ($token['error'] ?? 'codice ' . $response['status']) . ').');
        }

        $folder = $this->folderInfo((string) $token['access_token'], $host, (string) $meta['folder']);
        $summary = 'cartella "' . ($folder['name'] ?? $meta['folder']) . '"';
        $this->saveVerified(
            'oauth',
            ['access_token' => $token['access_token']],
            ['client_id' => $meta['client_id'], 'client_secret' => $meta['client_secret'], 'folder' => $meta['folder'], 'folder_id' => $folder['folderid'] ?? null, 'folder_name' => $folder['name'] ?? null, 'host' => $host],
            $summary
        );

        return $summary;
    }

    /** Il token di pCloud non scade: se non va piu', si ricollega. */
    protected function refreshAccessToken(ConnectorCredential $credential): ?string
    {
        return $credential->accessToken;
    }

    /** L'albero della cartella d'archivio, in una chiamata sola. */
    public function listFiles(): iterable
    {
        $folderId = $this->meta()['folder_id'] ?? null;
        if ($folderId === null) {
            return;
        }
        $tree = $this->call('listfolder', ['folderid' => (int) $folderId, 'recursive' => 1]);
        $queue = [[$tree['metadata'] ?? [], '']];
        while ($queue !== []) {
            [$folder, $path] = array_shift($queue);
            foreach ($folder['contents'] ?? [] as $item) {
                if (!empty($item['isfolder'])) {
                    $queue[] = [$item, ltrim($path . '/' . $item['name'], '/')];
                    continue;
                }
                $mime = self::mimeOf($item);
                if ($mime === null) {
                    continue;
                }
                yield [
                    'id' => (string) $item['fileid'],
                    'name' => (string) $item['name'],
                    'mime' => $mime,
                    'folder' => $path,
                    'webLink' => $this->webLink((int) ($item['parentfolderid'] ?? $folderId)),
                    'size' => isset($item['size']) ? (int) $item['size'] : null,
                    'modifiedAt' => isset($item['modified']) ? date('Y-m-d H:i:s', (int) strtotime((string) $item['modified'])) : null,
                    'width' => isset($item['width']) ? (int) $item['width'] : null,
                    'height' => isset($item['height']) ? (int) $item['height'] : null,
                    'duration' => isset($item['duration']) ? (int) round((float) $item['duration']) : null,
                ];
            }
        }
    }

    public function download(MediaAsset $asset, string $target, int $maxBytes): bool
    {
        if ($asset->sizeBytes !== null && $asset->sizeBytes > $maxBytes) {
            return false;
        }
        $url = $this->fileUrl('getfilelink', ['fileid' => (int) $asset->externalId]);
        if ($url === null) {
            return false;
        }
        $handle = fopen($target, 'wb');
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_FILE => $handle, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 600, CURLOPT_CONNECTTIMEOUT => 10]);
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        fclose($handle);

        return $ok !== false && $status === 200 && filesize($target) > 0;
    }

    public function previewImage(MediaAsset $asset): ?string
    {
        $url = $this->fileUrl('getthumblink', ['fileid' => (int) $asset->externalId, 'size' => '1280x1280']);
        if ($url === null) {
            return null;
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $status === 200 && is_string($body) && $body !== '' ? $body : null;
    }

    /**
     * La cartella dall'indirizzo di my.pcloud.com ("folder=123", come
     * numero) o un percorso ("/Clienti").
     */
    public static function folderFrom(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('/[#?&]folder=(\d+)/', $input, $m) === 1 || preg_match('/^\d+$/', $input, $m) === 1) {
            return $m[1] ?? $m[0];
        }

        return str_starts_with($input, '/') && strlen($input) > 1 ? rtrim($input, '/') : null;
    }

    /** @return ?string il tipo del file: immagini e video, gli altri no */
    private static function mimeOf(array $item): ?string
    {
        $type = (string) ($item['contenttype'] ?? '');
        if (str_starts_with($type, 'image/') || str_starts_with($type, 'video/')) {
            return $type;
        }

        return self::EXTENSIONS[strtolower(pathinfo((string) ($item['name'] ?? ''), PATHINFO_EXTENSION))] ?? null;
    }

    /** La cartella del file su pCloud (si apre con l'accesso dell'agenzia). */
    private function webLink(int $folderId): string
    {
        $web = ($this->meta()['host'] ?? '') === 'eapi.pcloud.com' ? 'https://e.pcloud.com' : 'https://my.pcloud.com';

        return "{$web}/#page=filemanager&folder={$folderId}";
    }

    private function fileUrl(string $method, array $params): ?string
    {
        $link = $this->call($method, $params);
        $host = $link['hosts'][0] ?? null;

        return $host !== null && isset($link['path']) ? 'https://' . $host . $link['path'] : null;
    }

    /** @return array{folderid?:int, name?:string} */
    private function folderInfo(?string $token = null, ?string $host = null, ?string $folder = null): array
    {
        $folder ??= (string) ($this->meta()['folder_id'] ?? $this->meta()['folder'] ?? '');
        $params = ctype_digit($folder) ? ['folderid' => (int) $folder] : ['path' => $folder];
        $info = $this->call('listfolder', $params + ['nofiles' => 1], $token, $host);
        if (!isset($info['metadata']['folderid'])) {
            throw new ConnectorException('Cartella non trovata su pCloud: controlla l\'indirizzo o il percorso.');
        }

        return $info['metadata'];
    }

    private function call(string $method, array $params, ?string $token = null, ?string $host = null): array
    {
        $token ??= $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('pCloud non e\' collegato: collegalo dalla Configurazione.');
        }
        $host ??= (string) ($this->meta()['host'] ?? self::HOSTS[0]);
        $response = Http::request('GET', "https://{$host}/{$method}?" . http_build_query($params), ["Authorization: Bearer {$token}"], null, 60);
        $json = $response['json'] ?? [];
        if (($json['result'] ?? null) !== 0) {
            $error = (string) ($json['error'] ?? 'codice ' . $response['status']);
            if (in_array((int) ($json['result'] ?? 0), [1000, 2000, 2094, 2095], true)) {
                throw new ConnectorException("pCloud non accetta piu' l'accesso ({$error}): ricollegalo dalla Configurazione.");
            }
            throw new ConnectorException("pCloud ha risposto: {$error}.");
        }

        return $json;
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
