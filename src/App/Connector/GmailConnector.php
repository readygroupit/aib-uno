<?php

declare(strict_types=1);

namespace App\Connector;

use App\Model\ConnectorCredential;

/**
 * La casella Gmail dell'agenzia in sola lettura: le email che arrivano dai
 * clienti diventano richieste da smistare (EmailRequestImporter, job
 * 'import_email_requests' ogni 5 minuti). Uno non invia, non cancella e
 * non segna come lette le email.
 *
 * OAuth come Google Calendar e Drive, con lo stesso callback e lo stesso
 * ID client: lo "state" comincia con 'gmail.' (vedi
 * SetupController::googleCallbackAction()). Si leggono solo le email
 * arrivate dopo il collegamento: il punto a cui si e' arrivati resta in
 * extra_config ('after', 'seen').
 */
final class GmailConnector extends AbstractOAuthConnector implements RedirectingConnectorInterface
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API = 'https://gmail.googleapis.com/gmail/v1/users/me';
    private const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';
    public const STATE_PREFIX = 'gmail.';
    /** Promozioni, social e notifiche non sono richieste dei clienti. */
    public const DEFAULT_QUERY = 'in:inbox -category:promotions -category:social -category:updates -category:forums';

    private ?string $pendingRedirect = null;

    public function code(): string
    {
        return 'gmail';
    }

    public function label(): string
    {
        return 'Gmail';
    }

    public function test(): bool
    {
        try {
            $this->profile();

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        $redirect = self::baseUrl() . GoogleCalendarConnector::CALLBACK_PATH;

        return [
            'intro' => 'Le email che i clienti mandano alla casella dell\'agenzia diventano richieste da smistare, con il cliente gia\' riconosciuto dall\'indirizzo: nessuno deve piu\' leggerle tutte per capire a chi tocca. Solo lettura: Uno non risponde, non cancella e non segna come lette le email.',
            'steps' => [
                'Su console.cloud.google.com, nel progetto del Calendario o del Drive, in "API e servizi", "Libreria" attiva "Gmail API".',
                'Se hai gia\' collegato Calendario o Drive usa lo stesso ID client e salta al passo 4. Altrimenti in "Google Auth Platform" (prima "Schermata consenso OAuth") compila nome app ed email; con un Gmail normale, in "Pubblico" premi "Pubblica app" (in prova l\'accesso scade dopo 7 giorni; con Google Workspace scegli invece "Interno").',
                'In "Client" (o "Credenziali", "ID client OAuth") crea un client "Applicazione web" con l\'URI di reindirizzamento autorizzato ' . $redirect . ' e copia ID client e Client secret.',
                'Incolla ID client e segreto e premi "Verifica e collega": si apre Google, entra con l\'account della casella (es. info@...) e consenti la lettura (se Google avvisa che l\'app non e\' verificata: "Avanzate", "Vai a ...").',
            ],
            'fields' => [
                ['key' => 'client_id', 'label' => 'ID client', 'type' => 'text', 'placeholder' => '1234-abc.apps.googleusercontent.com'],
                ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'placeholder' => 'GOCSPX-...'],
                ['key' => 'query', 'label' => 'Quali email leggere (facoltativo)', 'type' => 'text', 'placeholder' => self::DEFAULT_QUERY, 'help' => 'Una ricerca di Gmail, es. "label:clienti". Vuoto: la posta in arrivo senza promozioni, social e notifiche.'],
            ],
            'about' => [
                'purpose' => 'Le email che i clienti mandano alla casella dell\'agenzia non vanno piu\' lette da tutti: diventano richieste da smistare, gia\' con il cliente.',
                'auto' => [
                    'Ogni 5 minuti legge le email arrivate dopo il collegamento e, per ognuna di un cliente, crea una richiesta "nuova" (canale email, oggetto, testo e link per aprirla in Gmail).',
                    'Riconosce il cliente dall\'email del cliente o di un referente, o dal dominio del suo sito o della sua email aziendale (mai gmail.com e simili).',
                    'Salta colleghi, newsletter, risposte automatiche e sconosciuti; una risposta nella stessa conversazione non crea una richiesta nuova.',
                    'Non risponde, non cancella e non segna come lette le email.',
                ],
                'manual' => [
                    'Inserisci email e sito dei clienti e dei referenti: senza, non riconosce nessuno.',
                    'Chi smista legge la richiesta e la assegna: diventa l\'attivita\' di quella persona.',
                ],
                'where' => 'Richieste dei clienti (menu), tra le nuove da smistare.',
            ],
            'note' => 'Si leggono ogni 5 minuti solo le email arrivate dopo il collegamento e solo da indirizzi di clienti o referenti (o dal dominio del loro sito). Le risposte nella stessa conversazione non creano una richiesta nuova.',
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
        $query = trim($input['query'] ?? '');

        $state = self::STATE_PREFIX . bin2hex(random_bytes(16));
        $_SESSION['gmail_oauth_state'] = $state;
        $redirect = self::baseUrl() . GoogleCalendarConnector::CALLBACK_PATH;
        $this->savePending(['client_id' => $clientId, 'client_secret' => $clientSecret, 'query' => $query !== '' ? $query : null, 'redirect_uri' => $redirect]);

        $this->pendingRedirect = self::AUTH_URL . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return 'ora autorizza la lettura della posta su Google';
    }

    public function redirectUrl(): ?string
    {
        return $this->pendingRedirect;
    }

    public function completeAuthorization(string $code, string $state): string
    {
        $expected = $_SESSION['gmail_oauth_state'] ?? null;
        unset($_SESSION['gmail_oauth_state']);
        if ($expected === null || !hash_equals($expected, $state)) {
            throw new ConnectorException('La risposta di Google non corrisponde a questa richiesta. Riprova il collegamento.');
        }
        $meta = $this->meta();
        if (!isset($meta['client_id'], $meta['client_secret'])) {
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
            throw new ConnectorException('Google non ha dato un accesso permanente alla posta (' . ($token['error_description'] ?? $token['error'] ?? 'codice ' . $response['status']) . ').');
        }

        $profile = $this->profile((string) $token['access_token']);
        $mailbox = (string) ($profile['emailAddress'] ?? '');
        $summary = 'casella ' . ($mailbox !== '' ? $mailbox : 'collegata');
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
                'query' => $meta['query'] ?? null,
                'redirect_uri' => $meta['redirect_uri'] ?? null,
                'mailbox' => $mailbox,
                // Da ora in poi: la posta gia' arrivata non diventa una valanga di richieste.
                'after' => time(),
                'seen' => [],
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

    public function mailbox(): ?string
    {
        $mailbox = $this->meta()['mailbox'] ?? null;

        return is_string($mailbox) && $mailbox !== '' ? $mailbox : null;
    }

    /**
     * Le email arrivate dopo l'ultima lettura (le piu' vecchie prima), gia'
     * smontate: mittente, oggetto, testo. Non segna niente come letto: il
     * punto di arrivo si salva con markRead().
     *
     * @return list<array{id:string, threadId:string, internalDate:int, fromEmail:string, fromName:string, subject:string, date:string, text:string, link:string, automatic:bool}>
     */
    public function newMessages(int $limit = 50): array
    {
        $meta = $this->meta();
        $after = (int) ($meta['after'] ?? time());
        $seen = array_flip(array_map('strval', (array) ($meta['seen'] ?? [])));
        // after: di Gmail e' in secondi e include lo stesso secondo: i gia' visti si saltano.
        $query = trim(((string) ($meta['query'] ?? '') ?: self::DEFAULT_QUERY) . ' after:' . $after);

        $ids = [];
        $pageToken = null;
        do {
            $params = ['q' => $query, 'maxResults' => 100];
            if ($pageToken !== null) {
                $params['pageToken'] = $pageToken;
            }
            $page = $this->get('/messages?' . http_build_query($params));
            foreach ($page['messages'] ?? [] as $message) {
                $ids[] = (string) $message['id'];
            }
            $pageToken = $page['nextPageToken'] ?? null;
        } while ($pageToken !== null && count($ids) < 500);

        $messages = [];
        foreach ($ids as $id) {
            if (isset($seen[$id])) {
                continue;
            }
            $messages[] = self::parseMessage($this->get('/messages/' . rawurlencode($id) . '?format=full'), $this->mailbox());
        }
        usort($messages, static fn (array $a, array $b) => $a['internalDate'] <=> $b['internalDate']);

        return array_slice($messages, 0, $limit);
    }

    /**
     * Fin qui e' letto: il secondo dell'ultima email e le email di quel
     * secondo (per non rileggerle), piu' le conversazioni gia' diventate
     * richiesta (le ultime 500, per riconoscere le risposte).
     *
     * @param list<array{id:string, internalDate:int}> $messages
     * @param array<string,int> $threads conversazione => richiesta
     */
    public function markRead(array $messages, array $threads): void
    {
        $credential = $this->credential();
        if ($credential === null) {
            return;
        }
        $meta = $this->meta();
        $after = (int) ($meta['after'] ?? 0);
        $seen = array_map('strval', (array) ($meta['seen'] ?? []));
        foreach ($messages as $message) {
            $second = intdiv($message['internalDate'], 1000);
            if ($second > $after) {
                $after = $second;
                $seen = [];
            }
            if ($second === $after) {
                $seen[] = $message['id'];
            }
        }
        $meta['after'] = $after;
        $meta['seen'] = array_values(array_unique($seen));
        $meta['threads'] = array_slice(((array) ($meta['threads'] ?? [])) + $threads, -500, null, true);
        $meta['last_import_at'] = date('c');
        $this->credentials->update((int) $credential->id, ['extra_config' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);
    }

    /** @return array<string,int> conversazione => richiesta, gia' importate */
    public function knownThreads(): array
    {
        return array_map('intval', (array) ($this->meta()['threads'] ?? []));
    }

    /**
     * Un messaggio di Gmail (format=full) in chiaro.
     *
     * @return array{id:string, threadId:string, internalDate:int, fromEmail:string, fromName:string, subject:string, date:string, text:string, link:string, automatic:bool}
     */
    public static function parseMessage(array $message, ?string $mailbox = null): array
    {
        $headers = [];
        foreach ($message['payload']['headers'] ?? [] as $header) {
            $headers[strtolower((string) $header['name'])] = (string) $header['value'];
        }
        [$fromName, $fromEmail] = self::parseAddress($headers['from'] ?? '');
        $internalDate = (int) ($message['internalDate'] ?? 0);
        $text = self::bodyText($message['payload'] ?? []);
        if ($text === '') {
            $text = html_entity_decode((string) ($message['snippet'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        // Newsletter, risposte automatiche, notifiche: non le scrive una persona.
        $automatic = isset($headers['list-unsubscribe']) || isset($headers['list-id'])
            || in_array(strtolower($headers['precedence'] ?? ''), ['bulk', 'list', 'junk', 'auto_reply'], true)
            || (isset($headers['auto-submitted']) && strtolower($headers['auto-submitted']) !== 'no')
            || preg_match('/^(no-?reply|mailer-daemon|postmaster|notifications?)@/i', $fromEmail) === 1;
        $threadId = (string) ($message['threadId'] ?? $message['id'] ?? '');

        return [
            'id' => (string) ($message['id'] ?? ''),
            'threadId' => $threadId,
            'internalDate' => $internalDate,
            'fromEmail' => strtolower($fromEmail),
            'fromName' => $fromName,
            'subject' => trim(self::decodeHeader($headers['subject'] ?? '')),
            'date' => date('Y-m-d H:i:s', $internalDate > 0 ? intdiv($internalDate, 1000) : time()),
            'text' => self::withoutQuote($text),
            'link' => 'https://mail.google.com/mail/' . ($mailbox !== null ? '?authuser=' . rawurlencode($mailbox) : 'u/0/') . '#all/' . rawurlencode($threadId),
            'automatic' => $automatic,
        ];
    }

    /** @return array{0:string, 1:string} nome e indirizzo da "Mario Rossi <mario@x.it>" */
    private static function parseAddress(string $value): array
    {
        $value = self::decodeHeader($value);
        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>/', $value, $m) === 1) {
            return [trim($m[1]), trim($m[2])];
        }

        return ['', trim($value)];
    }

    private static function decodeHeader(string $value): string
    {
        return function_exists('iconv_mime_decode') ? (string) (iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: $value) : $value;
    }

    /** Il testo semplice; se c'e' solo HTML, l'HTML senza tag. */
    private static function bodyText(array $part): string
    {
        $plain = self::findPart($part, 'text/plain');
        if ($plain !== null) {
            return trim($plain);
        }
        $html = self::findPart($part, 'text/html');
        if ($html === null) {
            return '';
        }
        $html = preg_replace(['#<(style|script)[^>]*>.*?</\1>#is', '#<br\s*/?>#i', '#</(p|div|li|tr|h\d)>#i'], ['', "\n", "\n"], $html);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private static function findPart(array $part, string $mime): ?string
    {
        if (($part['mimeType'] ?? '') === $mime && isset($part['body']['data'])) {
            return (string) base64_decode(strtr((string) $part['body']['data'], '-_', '+/'));
        }
        foreach ($part['parts'] ?? [] as $child) {
            $found = self::findPart($child, $mime);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** Senza la parte citata della risposta ("Il giorno ... ha scritto:", righe con >). */
    private static function withoutQuote(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $cut = preg_split('/^(?:Il giorno .{5,200}ha scritto:|On .{5,200}wrote:|-{2,}\s*(?:Messaggio originale|Original Message)\s*-{2,}|Da: .+\nInviato: )/mu', $text, 2);
        $lines = array_filter(explode("\n", (string) ($cut[0] ?? $text)), static fn (string $line) => !str_starts_with(ltrim($line), '>'));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    private function profile(?string $token = null): array
    {
        $token ??= $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Gmail non e\' collegato.');
        }
        $response = Http::request('GET', self::API . '/profile', ["Authorization: Bearer {$token}"]);
        if ($response['status'] !== 200) {
            throw new ConnectorException('Gmail non risponde come previsto (codice ' . $response['status'] . '): controlla che la Gmail API sia attiva.');
        }

        return $response['json'] ?? [];
    }

    private function get(string $path): array
    {
        $token = $this->accessToken();
        if ($token === null) {
            throw new ConnectorException('Gmail non e\' collegato o l\'accesso e\' scaduto: ricollegalo dalla Configurazione.');
        }
        $response = Http::request('GET', self::API . $path, ["Authorization: Bearer {$token}"], null, 30);
        if ($response['status'] !== 200) {
            throw new ConnectorException('Gmail ha risposto con il codice ' . $response['status'] . '.');
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
