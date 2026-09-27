<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Container;
use App\Prompt\PromptToolRegistry;

/**
 * Chiamata diretta HTTP alla Messages API (niente SDK ufficiale: uno non
 * ha Composer per scelta - vedi le note di sessione). Un solo giro:
 * Claude sceglie una capacita' (tool use) o risponde con una domanda di
 * chiarimento (solo testo, nessun tool) - stesso comportamento che uso
 * io quando una richiesta non e' chiara.
 */
final class ClaudeService
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
        $apiKey = $this->config->get('claude.apiKey');
        if (!$apiKey) {
            throw new \RuntimeException("config/autoload/local.php: 'claude.apiKey' non impostata");
        }

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

        $payload = [
            'model' => $this->config->get('claude.model', 'claude-opus-4-8'),
            'max_tokens' => 1024,
            'system' => "Sei l'assistente conversazionale di Uno. Rispondi in italiano. "
                . 'Se la richiesta e\' chiara, usa uno strumento tra quelli disponibili. '
                . 'Se manca un\'informazione necessaria o la richiesta e\' ambigua, non usare '
                . 'nessuno strumento: fai una domanda di chiarimento breve, proponendo opzioni quando possibile.'
                . $contextNote,
            'messages' => $messages,
            'tools' => $this->buildToolsPayload(),
        ];

        $response = $this->call($payload, $apiKey);

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
        $apiKey = $this->config->get('claude.apiKey');
        if (!$apiKey) {
            throw new \RuntimeException("config/autoload/local.php: 'claude.apiKey' non impostata");
        }

        $payload = [
            'model' => $this->config->get('claude.model', 'claude-opus-4-8'),
            'max_tokens' => 512,
            'system' => "Sei un agente AI che assiste un operatore di un sistema gestionale. Proponi "
                . "un'azione concreta e breve (2-4 righe), in italiano, in base al task descritto. Non "
                . "stai eseguendo nulla direttamente: la tua proposta verra' sempre rivista da una "
                . 'persona prima di essere applicata, quindi scrivi un suggerimento chiaro e azionabile, '
                . 'non una domanda.',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];

        $response = $this->call($payload, $apiKey);

        $text = null;
        foreach ($response['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text = ($text ?? '') . $block['text'];
            }
        }

        return trim($text ?? '');
    }

    private function buildToolsPayload(): array
    {
        return array_map(static fn ($tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'input_schema' => $tool->inputSchema(),
        ], $this->registry->all());
    }

    private function call(array $payload, string $apiKey): array
    {
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'content-type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException("Chiamata a Claude fallita: {$error}");
        }

        $decoded = json_decode($body, true);
        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? $body;
            throw new \RuntimeException("Claude ha risposto {$status}: {$message}");
        }

        return $decoded;
    }
}
