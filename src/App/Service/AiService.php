<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Container;
use App\Prompt\PromptToolRegistry;

/**
 * Intelligenza artificiale del prompt e degli agenti, con fornitore
 * scelto da config 'ai.provider': 'openai' (Chat Completions) o 'claude'
 * (Messages API). Chiamate HTTP dirette, niente SDK (niente Composer per
 * scelta). Un solo giro: il modello sceglie una capacita' (tool) o
 * risponde con una domanda di chiarimento (solo testo, nessun tool).
 *
 * Chiave e modello in config/autoload/global.php -> 'ai' (sul PC si
 * possono sovrascrivere in local.php).
 */
final class AiService
{
    private Config $config;
    private PromptToolRegistry $registry;

    public function __construct(Container $container)
    {
        $this->config = $container->get(Config::class);
        $this->registry = $container->get(PromptToolRegistry::class);
    }

    /**
     * @param array<int,array{role:string,content:string}> $history
     * @param array{entities?: string[]} $context tipi di entita' (es.
     *     'user') attualmente visualizzati dall'operatore - non i dati,
     *     solo il tipo (vedi hero.js extractEntities()). Serve a risolvere
     *     un riferimento ambiguo (es. un nome proprio senza dire "utente")
     *     sul contesto invece di lasciarlo indovinare a caso o costringere
     *     sempre a una domanda di chiarimento.
     * @return array{reply: ?string, toolName: ?string, toolInput: ?array}
     */
    public function converse(string $message, array $history, array $context = []): array
    {
        $messages = $history;
        $messages[] = ['role' => 'user', 'content' => $message];

        $contextNote = '';
        $entities = $context['entities'] ?? [];
        if (!empty($entities)) {
            $list = implode(', ', $entities);
            $contextNote = " Contesto attuale: l'operatore ha davanti a se' un elenco/scheda di tipo "
                . "'{$list}'. Se il messaggio nomina qualcosa che potrebbe riferirsi a un elemento di "
                . 'questo tipo (es. un nome proprio senza specificare a cosa appartiene), preferisci '
                . "quell'interpretazione invece di chiedere chiarimento - la ricerca dello strumento "
                . 'copre comunque tutti i dati, non solo quelli visibili in questo momento. Se invece il '
                . 'messaggio menziona chiaramente un altro tipo di entita\', ignora questo contesto.';
        }

        $system = "Sei l'assistente conversazionale di " . APP_NAME . '. Rispondi in italiano. '
            . 'Ogni risposta passa da uno strumento. Se uno strumento risponde esattamente alla richiesta '
            . 'usalo subito, senza chiedere dettagli che non prevede (paginazione, formato). Se invece la '
            . 'richiesta chiede sui dati qualcosa che nessuno strumento copre esattamente (confronti tra '
            . 'periodi o anni, classifiche, incroci, raggruppamenti, statistiche particolari) usa '
            . 'analyze_data con la richiesta completa. Se la richiesta chiede semplicemente di vedere un tipo '
            . 'di dato (es. \'i rimborsi\', \'a che punto sono le pratiche\') usa il suo elenco; il report per il '
            . 'riepilogo generale della settimana. Per aprire una '
            . 'persona per nome senza dire cosa sia, cerca prima tra i contatti. Fai una domanda di '
            . 'chiarimento, breve e con opzioni, solo se nessuno strumento c\'entra o se il messaggio '
            . 'puo\' riferirsi a due elementi diversi (es. due persone con lo stesso nome).'
            . $contextNote;

        return $this->provider() === 'openai'
            ? $this->converseOpenAi($system, $messages)
            : $this->converseClaude($system, $messages);
    }

    /**
     * Chiamata semplice, senza tool: usata da RunAiAgentTaskHandler per
     * generare la proposta testuale di un task-agente (non un giro del
     * prompt conversazionale, quindi niente 'tools' - qui non deve mai
     * scegliere una capacita' del sistema, solo scrivere un suggerimento
     * che un operatore rivedra' prima di eseguirlo per davvero, coerente
     * con la supervisione umana richiesta dal documento Assilevi).
     */
    public function suggestAction(string $prompt): string
    {
        $system = "Sei un agente AI che assiste un operatore di un sistema gestionale. Proponi "
                . "un'azione concreta e breve (2-4 righe), in italiano, in base al task descritto. Non "
                . "stai eseguendo nulla direttamente: la tua proposta verra' sempre rivista da una "
                . 'persona prima di essere applicata, quindi scrivi un suggerimento chiaro e azionabile, '
                . 'non una domanda.';
        $messages = [['role' => 'user', 'content' => $prompt]];

        if ($this->provider() === 'openai') {
            $message = $this->callOpenAi($this->openAiPayload($system, $messages, 1024))['choices'][0]['message'] ?? [];

            return trim((string) ($message['content'] ?? ''));
        }

        $response = $this->callClaude([
            'model' => $this->settings('claude')['model'],
            'max_tokens' => 512,
            'system' => $system,
            'messages' => $messages,
        ]);
        $text = '';
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }
        }

        return trim($text);
    }

    /**
     * Risposta in JSON (oggetto) a una richiesta con istruzioni di sistema:
     * usata dall'analisi libera (App\Analysis) per il piano delle
     * interrogazioni e per la composizione della pagina.
     */
    public function json(string $system, string $user, int $maxTokens = 4000): array
    {
        if ($this->provider() === 'openai') {
            $payload = $this->openAiPayload($system, [['role' => 'user', 'content' => $user]], $maxTokens);
            $payload['response_format'] = ['type' => 'json_object'];
            $text = (string) ($this->callOpenAi($payload)['choices'][0]['message']['content'] ?? '');
        } else {
            $response = $this->callClaude([
                'model' => $this->settings('claude')['model'],
                'max_tokens' => $maxTokens,
                'system' => $system . "\nRispondi solo con un oggetto JSON valido, senza testo prima o dopo.",
                'messages' => [['role' => 'user', 'content' => $user]],
            ]);
            $text = '';
            foreach ($response['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $text .= $block['text'];
                }
            }
        }

        return self::decodeJson($text);
    }

    /**
     * Come json(), ma con delle immagini da guardare (foto, fotogrammi di
     * un video): l'IA le descrive secondo le istruzioni.
     *
     * @param list<array{mime:string, data:string}> $images data = binario dell'immagine (JPEG/PNG)
     */
    public function jsonWithImages(string $system, string $user, array $images, int $maxTokens = 2000): array
    {
        if ($this->provider() === 'openai') {
            $content = [['type' => 'text', 'text' => $user]];
            foreach ($images as $image) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime'] . ';base64,' . base64_encode($image['data'])]];
            }
            $payload = $this->openAiPayload($system, [['role' => 'user', 'content' => $content]], $maxTokens);
            $payload['response_format'] = ['type' => 'json_object'];
            $text = (string) ($this->callOpenAi($payload)['choices'][0]['message']['content'] ?? '');
        } else {
            $content = [];
            foreach ($images as $image) {
                $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => base64_encode($image['data'])]];
            }
            $content[] = ['type' => 'text', 'text' => $user];
            $response = $this->callClaude([
                'model' => $this->settings('claude')['model'],
                'max_tokens' => $maxTokens,
                'system' => $system . "\nRispondi solo con un oggetto JSON valido, senza testo prima o dopo.",
                'messages' => [['role' => 'user', 'content' => $content]],
            ]);
            $text = '';
            foreach ($response['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $text .= $block['text'];
                }
            }
        }

        return self::decodeJson($text);
    }

    private static function decodeJson(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        $decoded = $start !== false && $end !== false ? json_decode(substr($text, $start, $end - $start + 1), true) : null;
        if (!is_array($decoded)) {
            throw new \RuntimeException('Risposta dell\'IA non leggibile (JSON non valido).');
        }

        return $decoded;
    }

    /** Vero se c'e' una chiave per il fornitore scelto: senza, le funzioni IA si spengono con garbo. */
    public function isConfigured(): bool
    {
        $settings = $this->config->get('ai')[$this->provider()] ?? [];

        return !empty($settings['apiKey']);
    }

    /** @return array{reply: ?string, toolName: ?string, toolInput: ?array} */
    private function converseOpenAi(string $system, array $messages): array
    {
        $payload = $this->openAiPayload($system, $messages, 2048);
        $payload['tools'] = array_map(static fn ($tool) => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => self::objectSchema($tool->inputSchema()),
            ],
        ], $this->registry->all());

        $message = $this->callOpenAi($payload)['choices'][0]['message'] ?? [];
        $call = $message['tool_calls'][0]['function'] ?? null;
        $input = $call !== null ? json_decode((string) ($call['arguments'] ?? '{}'), true) : null;

        return [
            'reply' => isset($message['content']) && $message['content'] !== '' ? (string) $message['content'] : null,
            'toolName' => $call['name'] ?? null,
            'toolInput' => is_array($input) ? $input : ($call !== null ? [] : null),
        ];
    }

    /** @return array{reply: ?string, toolName: ?string, toolInput: ?array} */
    private function converseClaude(string $system, array $messages): array
    {
        $response = $this->callClaude([
            'model' => $this->settings('claude')['model'],
            'max_tokens' => 1024,
            'system' => $system,
            'messages' => $messages,
            'tools' => array_map(static fn ($tool) => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'input_schema' => $tool->inputSchema(),
            ], $this->registry->all()),
        ]);

        $toolUse = null;
        $text = null;
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use') {
                $toolUse = $block;
            } elseif (($block['type'] ?? null) === 'text') {
                $text = ($text ?? '') . $block['text'];
            }
        }

        return [
            'reply' => $text,
            'toolName' => $toolUse['name'] ?? null,
            'toolInput' => $toolUse['input'] ?? null,
        ];
    }

    private function openAiPayload(string $system, array $messages, int $maxTokens): array
    {
        $model = (string) $this->settings('openai')['model'];
        $payload = [
            'model' => $model,
            'messages' => [['role' => 'system', 'content' => $system], ...$messages],
            'max_completion_tokens' => $maxTokens,
        ];
        // I modelli che ragionano (gpt-5*, o*) spendono token anche per
        // pensare: poco ragionamento, risposta piu' rapida ed economica.
        if (preg_match('/^(gpt-5|o\d)/', $model) === 1) {
            $payload['reasoning_effort'] = 'low';
        }

        return $payload;
    }

    /** OpenAI vuole sempre un oggetto JSON per 'properties', anche vuoto. */
    private static function objectSchema(array $schema): array
    {
        $schema['type'] ??= 'object';
        if (empty($schema['properties'])) {
            $schema['properties'] = new \stdClass();
        }

        return $schema;
    }

    private function provider(): string
    {
        return (string) ($this->config->get('ai')['provider'] ?? 'openai');
    }

    /** @return array{apiKey: string, model: string} */
    private function settings(string $provider): array
    {
        $settings = $this->config->get('ai')[$provider] ?? [];
        if (empty($settings['apiKey'])) {
            throw new \RuntimeException("chiave API {$provider} non impostata (config 'ai.{$provider}.apiKey')");
        }

        return $settings;
    }

    private function callOpenAi(array $payload): array
    {
        return $this->post('https://api.openai.com/v1/chat/completions', $payload, [
            'authorization: Bearer ' . $this->settings('openai')['apiKey'],
        ], 'OpenAI');
    }

    private function callClaude(array $payload): array
    {
        return $this->post('https://api.anthropic.com/v1/messages', $payload, [
            'x-api-key: ' . $this->settings('claude')['apiKey'],
            'anthropic-version: 2023-06-01',
        ], 'Claude');
    }

    private function post(string $url, array $payload, array $headers, string $label): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['content-type: application/json', ...$headers],
            CURLOPT_POSTFIELDS => json_encode($payload),
            // Le immagini (fotogrammi dei video) richiedono piu' tempo.
            CURLOPT_TIMEOUT => 120,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("Chiamata a {$label} fallita: {$error}");
        }

        $decoded = json_decode($body, true);
        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? $body;
            throw new \RuntimeException("{$label} ha risposto {$status}: {$message}");
        }

        return $decoded;
    }
}
