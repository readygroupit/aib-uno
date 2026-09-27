<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\PromptToolInterface;
use App\Prompt\PromptToolRegistry;
use App\Service\AuthService;
use App\Service\ClaudeService;

final class PromptController extends AuthController
{
    public function indexAction(): void
    {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $message = trim((string) ($body['message'] ?? ''));
        $history = $body['history'] ?? [];
        $context = is_array($body['context'] ?? null) ? $body['context'] : [];

        if ($message === '') {
            $this->json(['reply' => 'Scrivi o pronuncia una richiesta.', 'components' => [], 'history' => $history]);
            return;
        }

        /** @var PromptToolRegistry $registry */
        $registry = $this->container->get(PromptToolRegistry::class);

        // Corrispondenza diretta con le frasi scorciatoia di una capacita':
        // se trovata, esegue subito senza spendere una chiamata a Claude
        // (piu' veloce, gratis, utile anche quando l'API non e' configurata
        // o non ha credito - vedi PromptToolInterface::triggers()).
        $localTool = $this->matchLocalTool($registry, $message);

        if ($localTool !== null) {
            $this->respondWithTool($localTool, [], $message, $history);
            return;
        }

        // Prototipo del mapping "verbo + entita' del contesto" descritto a
        // voce (vedi CONTEXT_VERBS piu' sotto): copre solo il caso a un
        // unico contesto visibile e un verbo fisso, ma basta a mostrare/
        // provare il comportamento finale ("modifica admin" mentre si
        // guarda la lista utenti apre la scheda) senza spendere una
        // chiamata Claude - utile proprio ora che l'API non ha credito.
        // Una frase diversa, o piu' contesti visibili insieme, restano
        // sempre di competenza di Claude qui sotto: questo NON e' un
        // parser di linguaggio naturale, solo un segnaposto dichiarato.
        $contextMatch = $this->matchContextualVerb($registry, $message, $context);

        if ($contextMatch !== null) {
            $this->respondWithTool($contextMatch['tool'], $contextMatch['input'], $message, $history);
            return;
        }

        /** @var ClaudeService $claude */
        $claude = $this->container->get(ClaudeService::class);

        try {
            $outcome = $claude->converse($message, $history, $context);
        } catch (\Throwable $e) {
            $this->json(['reply' => "Errore nel contattare Claude: {$e->getMessage()}", 'components' => [], 'history' => $history], 500);
            return;
        }

        $history[] = ['role' => 'user', 'content' => $message];
        $components = [];
        $reply = $outcome['reply'];

        if ($outcome['toolName'] !== null) {
            $tool = $registry->get($outcome['toolName']);
            $result = $this->executeToolWithPermission($tool, $outcome['toolInput'] ?? []);

            if ($result['denied']) {
                $reply = 'Non hai il permesso per eseguire questa richiesta.';
            } else {
                $components = $result['components'];
                $reply ??= '';
            }
        }

        $history[] = ['role' => 'assistant', 'content' => $reply ?? ''];

        $this->json(['reply' => $reply, 'components' => $components, 'history' => $history]);
    }

    private function matchLocalTool(PromptToolRegistry $registry, string $message): ?PromptToolInterface
    {
        $normalized = mb_strtolower(trim($message));

        foreach ($registry->all() as $tool) {
            foreach ($tool->triggers() as $trigger) {
                // Solo frase intera o frase-guida seguita da uno spazio
                // (es. "lista utenti pagina 2") - MAI una sottostringa
                // ovunque nel messaggio: "modifica users.manage" non deve
                // attivare il trigger 'users' di ListUsersTool solo
                // perche' compare dentro il codice di un permesso.
                if ($normalized === $trigger || str_starts_with($normalized, $trigger . ' ')) {
                    return $tool;
                }
            }
        }

        return null;
    }

    /**
     * Verbi che, seguiti dall'entita' del contesto, aprono la sua
     * scheda. Placeholder deliberatamente rozzo: un vero "capisce cosa
     * intendi" resta compito di Claude (vedi ClaudeService::converse() e
     * il 'contextNote' li' dentro, stesso principio ma con comprensione
     * vera del linguaggio).
     */
    private const CONTEXT_VERBS = ['modifica', 'apri'];

    /**
     * Il nome del tool si deriva dalla convenzione 'edit_' + entita' (es.
     * contesto 'customer' -> tool 'edit_customer') invece di un mapping
     * scritto a mano: ogni nuovo pacchetto con un AbstractEditEntityTool
     * ottiene gratis "modifica/apri X" senza dover registrare nulla qui -
     * prima di questa convenzione, un'entita' dimenticata nel mapping
     * (successo davvero con 'customer') restava silenziosamente non
     * raggiungibile da qui, ripiegando su Claude senza un motivo evidente.
     *
     * @param array{entities?: string[]} $context
     * @return array{tool: PromptToolInterface, input: array}|null
     */
    private function matchContextualVerb(PromptToolRegistry $registry, string $message, array $context): ?array
    {
        $entities = $context['entities'] ?? [];
        // Con piu' di un'entita' visibile insieme non c'e' modo di sapere
        // a quale ci si riferisce (e' esattamente il caso che a voce
        // avevamo lasciato a Claude) - meglio niente risultato che uno
        // sbagliato.
        if (count($entities) !== 1) {
            return null;
        }

        $toolName = 'edit_' . $entities[0];
        if (!$registry->has($toolName)) {
            return null;
        }

        $normalized = mb_strtolower(trim($message));

        foreach (self::CONTEXT_VERBS as $verb) {
            if (!str_starts_with($normalized, $verb . ' ')) {
                continue;
            }

            $query = trim(mb_substr($message, mb_strlen($verb) + 1));
            if ($query === '') {
                continue;
            }

            return ['tool' => $registry->get($toolName), 'input' => ['query' => $query]];
        }

        return null;
    }

    /**
     * Esegue un tool gia' risolto (da matchLocalTool o matchContextualVerb)
     * e risponde con lo stesso formato usato dopo una chiamata Claude -
     * un solo punto che costruisce reply/history/JSON, invece di ripetere
     * la stessa logica per ogni via che porta a un tool.
     */
    private function respondWithTool(PromptToolInterface $tool, array $input, string $message, array $history): void
    {
        $result = $this->executeToolWithPermission($tool, $input);
        $reply = $result['denied'] ? 'Non hai il permesso per eseguire questa richiesta.' : '';

        $history[] = ['role' => 'user', 'content' => $message];
        $history[] = ['role' => 'assistant', 'content' => $reply];

        $this->json(['reply' => $reply, 'components' => $result['components'], 'history' => $history]);
    }

    /**
     * @return array{denied: bool, components: array}
     */
    private function executeToolWithPermission(PromptToolInterface $tool, array $input): array
    {
        $permission = $tool->requiredPermission();

        /** @var AuthService $auth */
        $auth = $this->container->get(AuthService::class);

        if ($permission !== null && !$auth->hasPermission($permission)) {
            return ['denied' => true, 'components' => []];
        }

        return ['denied' => false, 'components' => $tool->execute($input)];
    }
}
