<?php

declare(strict_types=1);

namespace App\Connector;

/**
 * WhatsApp Business Cloud API di Meta: token permanente + id del numero.
 * Invia i messaggi ai clienti dal numero aziendale (WhatsAppService, dalle
 * finestre "link per il cliente"). WhatsApp accetta un messaggio libero
 * solo se il cliente ha scritto nelle ultime 24 ore; altrimenti serve un
 * modello approvato da Meta: se e' indicato, si usa sempre quello, con il
 * testo del messaggio come unica variabile {{1}}.
 */
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
                'Consigliato: in WhatsApp Manager, "Modelli di messaggio", crea un modello di categoria "Utilita\'" in italiano con un testo come "Ciao, ti scriviamo da ' . APP_NAME . ': {{1}}" e aspetta l\'approvazione (di solito pochi minuti). Scrivi qui sotto il suo nome: senza modello, WhatsApp consegna il messaggio solo ai clienti che vi hanno scritto nelle ultime 24 ore.',
            ],
            'fields' => [
                ['key' => 'access_token', 'label' => 'Token di accesso permanente', 'type' => 'password', 'placeholder' => 'Incolla qui il token'],
                ['key' => 'phone_number_id', 'label' => 'ID numero di telefono', 'type' => 'text', 'placeholder' => 'Solo cifre, es. 109876543210987'],
                ['key' => 'template', 'label' => 'Nome del modello approvato (consigliato)', 'type' => 'text', 'placeholder' => 'es. messaggio_cliente', 'help' => 'Con una sola variabile {{1}} nel testo: diventa il messaggio da mandare.'],
                ['key' => 'template_lang', 'label' => 'Lingua del modello', 'type' => 'text', 'placeholder' => 'it'],
            ],
            'about' => [
                'purpose' => 'Mandare ai clienti link e messaggi (preventivo, area cliente, contenuti da approvare, pagamento) dal numero WhatsApp Business dell\'azienda, con un clic, invece che dal telefono di ognuno.',
                'auto' => [
                    'Niente parte da solo: ogni messaggio lo manda una persona.',
                    'Usa il cellulare del cliente (o il telefono, se manca) dalla sua scheda.',
                    'In modalita\' prova il messaggio va al numero per le prove, o resta solo nel registro.',
                ],
                'manual' => [
                    'Nelle finestre "Link per il cliente" (preventivo, area cliente, piano editoriale, link di pagamento): "Invia su WhatsApp" manda il messaggio gia\' pronto.',
                    'Le risposte del cliente arrivano su WhatsApp Business (app o WhatsApp Manager), come sempre.',
                ],
                'where' => 'Preventivi, scheda cliente, piano editoriale, scadenzario.',
            ],
            'note' => 'Senza WhatsApp collegato, "Invia su WhatsApp" apre comunque WhatsApp sul tuo computer o telefono con il messaggio gia\' scritto (lo mandi tu dal tuo numero).',
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

        $template = trim($input['template'] ?? '');
        if ($template !== '' && preg_match('/^[a-z0-9_]+$/', $template) !== 1) {
            throw new ConnectorException('Il nome del modello ha solo lettere minuscole, numeri e trattini bassi (es. messaggio_cliente).');
        }
        $summary = $this->fetchNumber($token, $phoneId);
        $this->saveVerified('api_key', ['api_key' => $token], [
            'phone_number_id' => $phoneId,
            'template' => $template !== '' ? $template : null,
            'template_lang' => trim($input['template_lang'] ?? '') ?: 'it',
        ], $summary . ($template !== '' ? ", modello \"{$template}\"" : ', senza modello'));

        return $summary;
    }

    /**
     * Manda un messaggio al numero indicato (internazionale, solo cifre,
     * es. 393331234567): con il modello, se c'e', altrimenti testo libero.
     *
     * @return string l'id del messaggio su WhatsApp
     */
    public function send(string $to, string $text): string
    {
        $key = $this->apiKey();
        $meta = $this->meta();
        $phoneId = $meta['phone_number_id'] ?? null;
        if ($key === null || !is_string($phoneId)) {
            throw new ConnectorException('WhatsApp non e\' collegato: collegalo dalla Configurazione.');
        }
        $template = $meta['template'] ?? null;
        $body = is_string($template) && $template !== ''
            ? [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => (string) ($meta['template_lang'] ?? 'it')],
                    // Le variabili dei modelli non accettano a capo ne' tabulazioni.
                    'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr(trim((string) preg_replace('/\s+/u', ' ', $text)), 0, 1000)]]]],
                ],
            ]
            : ['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['preview_url' => true, 'body' => mb_substr($text, 0, 4096)]];

        $response = Http::request('POST', self::GRAPH . "/{$phoneId}/messages", ["Authorization: Bearer {$key}", 'Content-Type: application/json'], (string) json_encode($body, JSON_UNESCAPED_UNICODE), 20);
        $id = $response['json']['messages'][0]['id'] ?? null;
        if ($response['status'] !== 200 || !is_string($id)) {
            $error = $response['json']['error'] ?? [];
            $detail = (string) ($error['error_data']['details'] ?? $error['message'] ?? ('codice ' . $response['status']));
            throw new ConnectorException('WhatsApp non ha accettato il messaggio: ' . $detail);
        }

        return $id;
    }

    public function hasTemplate(): bool
    {
        return !empty($this->meta()['template']);
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
