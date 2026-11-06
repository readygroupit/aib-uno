<?php

declare(strict_types=1);

namespace App\Connector;

use App\Core\Container;
use App\Model\ConnectorCredential;
use App\Model\Customer;
use App\Model\SocialAccount;
use App\Repository\CustomerRepository;
use App\Repository\SocialAccountRepository;

/**
 * Facebook e Instagram dei clienti (Meta Graph API): con l'accesso di chi
 * in agenzia gestisce le pagine dei clienti si leggono le pagine Facebook e
 * i profili Instagram professionali collegati (tabella social_accounts),
 * ognuno si assegna a un cliente (Configurazione, "Pagine e profili
 * social") e da li' si pubblicano i contenuti del piano editoriale
 * (SocialPublisher).
 *
 * OAuth di Facebook Login con un'app Meta dell'agenzia (tipo "Business"),
 * ritorno a /configurazione/meta/callback. Il token dell'utente dura 60
 * giorni; quelli delle pagine, ricavati da un token lungo, non scadono: si
 * pubblica con quelli. Instagram pubblica con il token della pagina
 * Facebook a cui il profilo e' collegato.
 */
final class MetaConnector extends AbstractOAuthConnector implements RedirectingConnectorInterface
{
    private const VERSION = 'v21.0';
    private const GRAPH = 'https://graph.facebook.com/' . self::VERSION;
    private const DIALOG = 'https://www.facebook.com/' . self::VERSION . '/dialog/oauth';
    public const CALLBACK_PATH = '/configurazione/meta/callback';
    private const SCOPES = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'instagram_basic', 'instagram_content_publish', 'business_management'];

    private ?string $pendingRedirect = null;
    private SocialAccountRepository $accounts;
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->accounts = $container->get(SocialAccountRepository::class);
    }

    public function code(): string
    {
        return 'meta';
    }

    public function label(): string
    {
        return 'Facebook e Instagram';
    }

    public function test(): bool
    {
        try {
            $this->graph('GET', '/me', ['fields' => 'id'], (string) $this->accessToken());

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        $redirect = self::baseUrl() . self::CALLBACK_PATH;

        return [
            'intro' => 'Pubblica i contenuti del piano editoriale sulle pagine Facebook e sui profili Instagram dei clienti, all\'ora di uscita o con un clic.',
            'steps' => [
                'Su developers.facebook.com, con l\'account Facebook di chi in agenzia gestisce le pagine dei clienti, crea un\'app di tipo "Business" (o "Altro", poi "Business").',
                'Nell\'app aggiungi il prodotto "Facebook Login for Business" (o "Facebook Login") e in "Impostazioni" metti tra gli "URI di reindirizzamento OAuth validi": ' . $redirect,
                'In "Impostazioni dell\'app", "Di base" copia "ID app" e "Chiave segreta" nei campi qui sotto. Finche\' l\'app non passa la revisione di Meta, possono collegarla solo le persone con un ruolo nell\'app (amministratore, sviluppatore, tester): basta per l\'uso dell\'agenzia.',
                'Le pagine dei clienti devono essere gestite da quell\'account (direttamente o dal Business Manager), e ogni profilo Instagram deve essere "professionale" e collegato alla pagina Facebook del cliente.',
                'Premi "Verifica e collega": si apre Facebook, scegli le pagine e i profili da collegare e consenti tutto. Poi, in "Pagine e profili social", assegna ognuno al suo cliente.',
            ],
            'fields' => [
                ['key' => 'app_id', 'label' => 'ID app', 'type' => 'text', 'placeholder' => '1234567890123456'],
                ['key' => 'app_secret', 'label' => 'Chiave segreta dell\'app', 'type' => 'password'],
            ],
            'about' => [
                'purpose' => 'I contenuti approvati escono sui social dei clienti senza copiare testi e immagini a mano: dal piano editoriale, con "Pubblica ora" o all\'ora di uscita.',
                'auto' => [
                    'Legge le pagine Facebook e i profili Instagram che l\'account gestisce e prova ad assegnarli ai clienti con lo stesso nome.',
                    'Ogni 5 minuti pubblica i contenuti "programmati" arrivati all\'ora di uscita (con i file caricati), poi li segna pubblicati con il link al post.',
                    'In modalita\' prova non pubblica: registra cosa sarebbe uscito e segna il contenuto come pubblicato in prova.',
                ],
                'manual' => [
                    'In "Pagine e profili social" controlli a quale cliente va ogni pagina o profilo.',
                    'Nel contenuto: carichi i file da pubblicare (foto JPG/PNG o video MP4), poi "Pubblica ora" o "Programma la pubblicazione".',
                    'Instagram: post, carosello (2-10 foto/video), reel e storie. Facebook: post con foto, piu\' foto, video o solo testo.',
                ],
                'where' => 'Piano editoriale (pagina del contenuto) e Configurazione, "Pagine e profili social".',
            ],
            'note' => 'TikTok e LinkedIn non sono collegati: le loro API per pubblicare richiedono l\'approvazione dell\'app da parte delle due piattaforme. I file devono essere raggiungibili da Meta: in produzione va bene, dal computer di sviluppo no.',
        ];
    }

    public function connect(array $input): string
    {
        $appId = trim($input['app_id'] ?? '');
        $secret = trim($input['app_secret'] ?? '');
        if (!ctype_digit($appId) || strlen($appId) < 10) {
            throw new ConnectorException('L\'ID app e\' un numero di 15-16 cifre: lo trovi in "Impostazioni dell\'app", "Di base".');
        }
        if (strlen($secret) < 20) {
            throw new ConnectorException('Manca la chiave segreta dell\'app (32 caratteri).');
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION['meta_oauth_state'] = $state;
        $redirect = self::baseUrl() . self::CALLBACK_PATH;
        $this->savePending(['app_id' => $appId, 'app_secret' => $secret, 'redirect_uri' => $redirect]);
        $this->pendingRedirect = self::DIALOG . '?' . http_build_query([
            'client_id' => $appId,
            'redirect_uri' => $redirect,
            'state' => $state,
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
        ]);

        return 'ora autorizza l\'accesso su Facebook';
    }

    public function redirectUrl(): ?string
    {
        return $this->pendingRedirect;
    }

    public function completeAuthorization(string $code, string $state): string
    {
        $expected = $_SESSION['meta_oauth_state'] ?? null;
        unset($_SESSION['meta_oauth_state']);
        if ($expected === null || !hash_equals($expected, $state)) {
            throw new ConnectorException('La risposta di Facebook non corrisponde a questa richiesta. Riprova il collegamento.');
        }
        $meta = $this->meta();
        if (!isset($meta['app_id'], $meta['app_secret'])) {
            throw new ConnectorException('Mancano i dati del collegamento: ricomincia dalla Configurazione.');
        }
        $short = $this->graph('GET', '/oauth/access_token', [
            'client_id' => $meta['app_id'],
            'client_secret' => $meta['app_secret'],
            'redirect_uri' => $meta['redirect_uri'] ?? self::baseUrl() . self::CALLBACK_PATH,
            'code' => $code,
        ]);
        // Token lungo (60 giorni): i token delle pagine ricavati da questo non scadono.
        $long = $this->graph('GET', '/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $meta['app_id'],
            'client_secret' => $meta['app_secret'],
            'fb_exchange_token' => (string) ($short['access_token'] ?? ''),
        ]);
        $token = (string) ($long['access_token'] ?? '');
        if ($token === '') {
            throw new ConnectorException('Facebook non ha dato un accesso valido.');
        }
        $me = $this->graph('GET', '/me', ['fields' => 'name'], $token);
        $this->saveVerified(
            'oauth',
            ['access_token' => $token, 'token_expires_at' => date('Y-m-d H:i:s', time() + (int) ($long['expires_in'] ?? 5184000))],
            ['app_id' => $meta['app_id'], 'app_secret' => $meta['app_secret'], 'redirect_uri' => $meta['redirect_uri'] ?? null, 'user_name' => $me['name'] ?? null],
            'collegato'
        );
        $summary = $this->refreshAccounts();

        return $summary;
    }

    /** Il token lungo non si rinnova da solo: dopo 60 giorni si ricollega (le pagine continuano a pubblicare). */
    protected function refreshAccessToken(ConnectorCredential $credential): ?string
    {
        return $credential->accessToken;
    }

    /**
     * Rilegge pagine e profili dall'account collegato e li salva; un
     * account nuovo si assegna al cliente con lo stesso nome, se c'e'.
     *
     * @return string riepilogo ("6 pagine Facebook, 5 profili Instagram")
     */
    public function refreshAccounts(): string
    {
        $token = $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Facebook e Instagram non sono collegati.');
        }
        $pages = [];
        $after = null;
        do {
            $params = ['fields' => 'id,name,access_token,instagram_business_account{id,username,name}', 'limit' => 100];
            if ($after !== null) {
                $params['after'] = $after;
            }
            $page = $this->graph('GET', '/me/accounts', $params, $token);
            $pages = array_merge($pages, $page['data'] ?? []);
            $after = isset($page['paging']['next']) ? ($page['paging']['cursors']['after'] ?? null) : null;
        } while ($after !== null && count($pages) < 500);

        $matchers = $this->customerMatchers();
        $seen = [];
        $facebook = 0;
        $instagram = 0;
        foreach ($pages as $page) {
            $pageToken = (string) ($page['access_token'] ?? '');
            $seen[] = $this->upsert('facebook', (string) $page['id'], (string) $page['name'], null, (string) $page['id'], $pageToken, $matchers);
            $facebook++;
            $ig = $page['instagram_business_account'] ?? null;
            if (is_array($ig) && isset($ig['id'])) {
                $seen[] = $this->upsert('instagram', (string) $ig['id'], (string) ($ig['name'] ?? $ig['username'] ?? $page['name']), $ig['username'] ?? null, (string) $page['id'], $pageToken, $matchers);
                $instagram++;
            }
        }
        // Spariti dall'account (accesso tolto): fuori, ma il cliente assegnato resta se tornano.
        foreach ($this->accounts->findAll() as $account) {
            if (!in_array((int) $account->id, $seen, true)) {
                $this->accounts->update((int) $account->id, ['status' => 0, 'access_token' => null]);
            }
        }
        $summary = "{$facebook} " . ($facebook === 1 ? 'pagina Facebook' : 'pagine Facebook') . ", {$instagram} " . ($instagram === 1 ? 'profilo Instagram' : 'profili Instagram');
        $credential = $this->credential();
        if ($credential !== null) {
            $meta = $this->meta();
            $meta['summary'] = $summary;
            $meta['accounts_at'] = date('c');
            $this->credentials->update((int) $credential->id, ['extra_config' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
        }

        return $summary;
    }

    /**
     * Pubblica su Instagram. $files: indirizzi pubblici (https) delle foto
     * JPG o dei video MP4, nell'ordine; $format: post, carosello, reel,
     * storia, video.
     *
     * @return array{id: string, url: ?string}
     */
    public function publishInstagram(SocialAccount $account, array $files, string $caption, string $format): array
    {
        $token = (string) $account->accessToken;
        $user = (string) $account->externalId;
        if ($files === []) {
            throw new ConnectorException('Instagram vuole almeno una foto o un video: caricali nel contenuto.');
        }
        if ($format === 'storia') {
            $container = $this->container($user, $files[0], ['media_type' => 'STORIES'], $token);
        } elseif (count($files) > 1) {
            $children = [];
            foreach (array_slice($files, 0, 10) as $file) {
                $children[] = $this->container($user, $file, ['is_carousel_item' => 'true'], $token);
            }
            $container = $this->createContainer($user, ['media_type' => 'CAROUSEL', 'children' => implode(',', $children), 'caption' => $caption], $token);
        } else {
            // Un video su Instagram oggi e' sempre un reel.
            $container = $this->container($user, $files[0], ['caption' => $caption] + (self::isVideo($files[0]['url']) ? ['media_type' => 'REELS'] : []), $token);
        }
        $this->waitReady($container, $token);
        $published = $this->graph('POST', "/{$user}/media_publish", ['creation_id' => $container], $token);
        $id = (string) ($published['id'] ?? '');
        $info = $id !== '' ? $this->graph('GET', "/{$id}", ['fields' => 'permalink'], $token) : [];

        return ['id' => $id, 'url' => $info['permalink'] ?? null];
    }

    /**
     * Pubblica sulla pagina Facebook: una foto, piu' foto, un video o solo testo.
     *
     * @return array{id: string, url: ?string}
     */
    public function publishFacebook(SocialAccount $account, array $files, string $caption): array
    {
        $token = (string) $account->accessToken;
        $page = (string) $account->externalId;
        $videos = array_values(array_filter($files, static fn (array $f) => self::isVideo($f['url'])));
        if ($videos !== []) {
            $result = $this->graph('POST', "/{$page}/videos", ['file_url' => $videos[0]['url'], 'description' => $caption], $token, 300);
            $id = (string) ($result['id'] ?? '');

            return ['id' => $id, 'url' => $id !== '' ? "https://www.facebook.com/{$page}/videos/{$id}" : null];
        }
        if (count($files) === 1) {
            $result = $this->graph('POST', "/{$page}/photos", ['url' => $files[0]['url'], 'caption' => $caption], $token, 120);
            $id = (string) ($result['post_id'] ?? $result['id'] ?? '');
        } else {
            $params = ['message' => $caption];
            foreach (array_values($files) as $i => $file) {
                $photo = $this->graph('POST', "/{$page}/photos", ['url' => $file['url'], 'published' => 'false'], $token, 120);
                $params["attached_media[{$i}]"] = json_encode(['media_fbid' => $photo['id'] ?? '']);
            }
            $result = $this->graph('POST', "/{$page}/feed", $params, $token, 120);
            $id = (string) ($result['id'] ?? '');
        }

        return ['id' => $id, 'url' => $id !== '' ? 'https://www.facebook.com/' . $id : null];
    }

    private function container(string $user, array $file, array $extra, string $token): string
    {
        $media = self::isVideo($file['url'])
            ? ['video_url' => $file['url']] + (isset($extra['media_type']) ? [] : ['media_type' => 'VIDEO'])
            : ['image_url' => $file['url']];

        return $this->createContainer($user, $media + $extra, $token);
    }

    private function createContainer(string $user, array $params, string $token): string
    {
        $result = $this->graph('POST', "/{$user}/media", $params, $token, 120);
        $id = (string) ($result['id'] ?? '');
        if ($id === '') {
            throw new ConnectorException('Instagram non ha preparato il contenuto.');
        }

        return $id;
    }

    /** I video Instagram si elaborano prima di poterli pubblicare: si aspetta fino a ~2 minuti. */
    private function waitReady(string $container, string $token): void
    {
        for ($i = 0; $i < 24; $i++) {
            $status = (string) ($this->graph('GET', "/{$container}", ['fields' => 'status_code'], $token)['status_code'] ?? 'FINISHED');
            if ($status === 'FINISHED') {
                return;
            }
            if ($status === 'ERROR' || $status === 'EXPIRED') {
                throw new ConnectorException('Instagram non e\' riuscito a elaborare il file (formato o durata non accettati).');
            }
            sleep(5);
        }
        throw new ConnectorException('Instagram sta ancora elaborando il video: riprova tra qualche minuto.');
    }

    private static function isVideo(string $url): bool
    {
        return preg_match('/\.(mp4|mov|m4v)(\?|$)/i', $url) === 1;
    }

    /** @param array<int,string> $matchers */
    private function upsert(string $network, string $externalId, string $name, ?string $username, string $pageId, string $token, array $matchers): int
    {
        $data = ['name' => mb_substr($name, 0, 150), 'username' => $username, 'page_id' => $pageId, 'access_token' => $token, 'status' => 1];
        $existing = $this->accounts->findByExternal($network, $externalId);
        if ($existing !== null) {
            $this->accounts->update((int) $existing->id, $data);

            return (int) $existing->id;
        }

        return $this->accounts->insert($data + [
            'network' => $network,
            'external_id' => $externalId,
            'customer_id' => self::match($name . ' ' . $username, $matchers),
        ]);
    }

    /** @return array<int,string> cliente => nome semplificato */
    private function customerMatchers(): array
    {
        $result = [];
        try {
            foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
                /** @var Customer $customer */
                $key = self::normalize($customer->displayName());
                if (strlen($key) >= 4) {
                    $result[(int) $customer->id] = $key;
                }
            }
        } catch (\Throwable) {
            // Senza clienti si assegna tutto a mano.
        }

        return $result;
    }

    /** @param array<int,string> $matchers */
    private static function match(string $name, array $matchers): ?int
    {
        $key = self::normalize($name);
        foreach ($matchers as $id => $customer) {
            if (str_contains($key, $customer)) {
                return $id;
            }
        }

        return null;
    }

    private static function normalize(string $text): string
    {
        $text = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text);
        $text = preg_replace('/\b(srl|srls|spa|snc|sas)\b/', '', $text);

        return (string) preg_replace('/[^a-z0-9]/', '', (string) $text);
    }

    /** @return array<string,mixed> */
    private function graph(string $method, string $path, array $params, ?string $token = null, int $timeout = 30): array
    {
        $headers = $token !== null && $token !== '' ? ["Authorization: Bearer {$token}"] : [];
        $url = self::GRAPH . $path;
        $body = null;
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $body = http_build_query($params);
        }
        $response = Http::request($method, $url, $headers, $body, $timeout);
        $json = $response['json'] ?? [];
        if ($response['status'] !== 200 || isset($json['error'])) {
            $error = $json['error'] ?? [];
            $message = (string) ($error['error_user_msg'] ?? $error['message'] ?? ('codice ' . $response['status']));
            if ((int) ($error['code'] ?? 0) === 190) {
                throw new ConnectorException('Meta non accetta piu\' l\'accesso (scaduto o revocato): ricollega Facebook e Instagram dalla Configurazione.');
            }
            throw new ConnectorException('Meta ha risposto: ' . $message);
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
