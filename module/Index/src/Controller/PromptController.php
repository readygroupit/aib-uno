<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Prompt\PromptToolRegistry;
use App\Prompt\Tool\AbstractEditEntityTool;
use App\Service\AuthService;
use App\Service\AiService;

final class PromptController extends AuthController
{
    public function indexAction(): void
    {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?? [];
        $message = trim((string) ($body['message'] ?? ''));
        $history = $body['history'] ?? [];
        $context = is_array($body['context'] ?? null) ? $body['context'] : [];

        if ($message === '') {
            $this->json(['reply' => 'Scrivi o pronuncia una richiesta.', 'components' => [], 'suggestions' => [], 'history' => $history]);
            return;
        }

        /** @var PromptToolRegistry $registry */
        $registry = $this->container->get(PromptToolRegistry::class);

        // Corrispondenza diretta con le frasi scorciatoia di una capacita':
        // se trovata, esegue subito senza spendere una chiamata a Claude
        // (piu' veloce, gratis, utile anche quando l'API non e' configurata
        // o non ha credito - vedi PromptToolInterface::triggers()).
        // Una voce del menu dice gia' quale capacita' aprire.
        $requested = is_string($body['tool'] ?? null) ? $body['tool'] : '';
        $localMatch = $requested !== '' && $registry->has($requested)
            ? ['tool' => $registry->get($requested), 'input' => []]
            : $this->matchLocalTool($registry, $message);

        // "Nuovo preventivo", "aggiungi un referente": il form vuoto, senza IA.
        $localMatch ??= $this->matchCreation($registry, $message);
        if (isset($localMatch['choices'])) {
            $history[] = ['role' => 'user', 'content' => $message];
            $history[] = ['role' => 'assistant', 'content' => 'Cosa vuoi inserire?'];
            $this->json(['reply' => 'Cosa vuoi inserire?', 'components' => [], 'suggestions' => $localMatch['choices'], 'history' => $history]);
            return;
        }

        if ($localMatch !== null) {
            $this->respondWithTool($localMatch['tool'], $localMatch['input'], $message, $history);
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

        /** @var AiService $claude */
        $ai = $this->container->get(AiService::class);

        try {
            $outcome = $ai->converse($message, $history, $context);
        } catch (\Throwable $e) {
            $this->json([
                'reply' => "Non sono riuscito a contattare l'intelligenza artificiale ({$e->getMessage()}). Riprova tra poco, oppure usa una delle frasi che conosco a memoria: il nome di una pagina del menu (es. \"clienti\", \"oggi\") o \"nuovo\" seguito da cosa vuoi inserire (es. \"nuovo preventivo\").",
                'components' => [],
                'suggestions' => [],
                'history' => $history,
            ], 500);
            return;
        }

        $history[] = ['role' => 'user', 'content' => $message];
        $components = [];
        $suggestions = [];
        $reply = $outcome['reply'];

        if ($outcome['toolName'] !== null) {
            $tool = $registry->get($outcome['toolName']);
            $result = $this->executeToolWithPermission($tool, $outcome['toolInput'] ?? []);

            if ($result['denied']) {
                $reply = 'Non hai il permesso per eseguire questa richiesta.';
            } else {
                $components = $result['components'];
                if ($tool instanceof PromptConversationalInterface) {
                    // La frase del tool (es. l'analisi libera) se l'IA non ne ha scritta una sua.
                    if ($reply === null || trim($reply) === '') {
                        $reply = $tool->replySummary($outcome['toolInput'] ?? []);
                    }
                    $suggestions = $tool->followUpSuggestions();
                }
                $reply ??= '';
            }
        } elseif ($reply === null || trim($reply) === '') {
            // Claude non ha scelto nessun tool ED e' rimasto senza niente
            // da dire: e' il caso "non ho capito la richiesta" - una
            // bolla vuota sotto l'input sarebbe peggio di un errore
            // esplicito (segnalato dall'utente).
            $reply = "Non sono sicuro di aver capito. Puoi riformulare la richiesta, magari con un nome piu' preciso (es. \"apri pratica 12\" invece di \"quella pratica\")?";
        }

        $history[] = ['role' => 'assistant', 'content' => $reply ?? ''];

        $this->json(['reply' => $reply, 'components' => $components, 'suggestions' => $suggestions, 'history' => $history]);
    }

    /**
     * Solo la frase intera (al massimo con "pagina N" in fondo). Prima
     * bastava che il messaggio COMINCIASSE con una frase scorciatoia, e
     * richieste piu' lunghe finivano nella schermata sbagliata senza mai
     * arrivare all'IA: "cosa devo fare domani" apriva oggi, "oggi Fabrizio
     * ha un appuntamento?" la propria giornata, "clienti a rischio" la
     * lista clienti. Tutto cio' che dice qualcosa in piu' lo capisce l'IA.
     *
     * @return array{tool: PromptToolInterface, input: array}|null
     */
    private function matchLocalTool(PromptToolRegistry $registry, string $message): ?array
    {
        $normalized = rtrim(mb_strtolower(trim($message)), " ?!.");
        $input = [];
        if (preg_match('/^(.+?)\s+pagina\s+(\d+)$/u', $normalized, $m) === 1) {
            $normalized = $m[1];
            $input = ['page' => (int) $m[2]];
        }

        foreach ($registry->all() as $tool) {
            if (in_array($normalized, $tool->triggers(), true)) {
                return ['tool' => $tool, 'input' => $input];
            }
        }

        return null;
    }

    /** Verbi di inserimento: seguiti dal nome di un elemento aprono il suo form vuoto. */
    private const CREATION_VERBS = ['nuovo', 'nuova', 'aggiungi', 'crea', 'inserisci', 'segna', 'registra'];

    /**
     * "Nuovo preventivo", "aggiungi un referente", "segna una spesa", o il
     * titolo esatto del form ("Chiedi ferie o un permesso"): apre il form
     * vuoto come farebbe l'IA con new=true, ma subito e senza chiamarla.
     * Solo verbo + nome e nient'altro: "nuovo appuntamento con Rossano
     * giovedi'" ha dati da mettere nel form e resta all'IA. Se il nome va
     * bene per due elementi ("nuovo servizio": del listino o di un
     * cliente?) vince il titolo esatto del form; se non c'e', si chiede quale.
     *
     * @return array{tool: PromptToolInterface, input: array}|array{choices: list<string>}|null
     */
    private function matchCreation(PromptToolRegistry $registry, string $message): ?array
    {
        $words = self::creationWords($message);
        if (count($words) < 2) {
            return null;
        }
        $isCreation = in_array($words[0], self::CREATION_VERBS, true);
        $exact = [];
        $found = [];
        foreach ($registry->all() as $tool) {
            if (!$tool instanceof AbstractEditEntityTool) {
                continue;
            }
            $names = $tool->creationNames();
            $noun = self::creationWords($names['noun']);
            if ($words === self::creationWords($names['title'])) {
                $exact[] = $tool;
            // Il nome anche solo all'inizio: "nuova richiesta" e' la "richiesta del cliente".
            } elseif ($isCreation && $noun !== [] && array_slice($words, 1) === array_slice($noun, 0, count($words) - 1)) {
                $found[] = $tool;
            }
        }
        // Il titolo esatto del form vince: "nuovo servizio" e' il servizio del listino.
        $found = $exact !== [] ? $exact : $found;
        if (count($found) === 1) {
            return ['tool' => $found[0], 'input' => ['new' => true]];
        }

        // Piu' elementi con quel nome: si chiede quale, con i titoli dei form (che si riconoscono da soli).
        return count($found) > 1 ? ['choices' => array_map(static fn (AbstractEditEntityTool $t) => $t->creationNames()['title'], $found)] : null;
    }

    /** Le parole che contano: minuscole, senza punteggiatura e articoli ("un'", "una", "il"...). */
    private static function creationWords(string $text): array
    {
        $text = preg_replace("/[^\\p{L}\\p{N}]+/u", ' ', mb_strtolower(str_replace(['’', "'"], "' ", $text)));

        return array_values(array_filter(
            explode(' ', (string) $text),
            static fn (string $w) => $w !== '' && !in_array($w, ['un', 'uno', 'una', 'il', 'lo', 'la', 'l', 'i', 'gli', 'le'], true)
        ));
    }

    /**
     * Verbi che, seguiti dall'entita' del contesto, aprono la sua
     * scheda. Placeholder deliberatamente rozzo: un vero "capisce cosa
     * intendi" resta compito di Claude (vedi AiService::converse() e
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
        $suggestions = [];

        if ($result['denied']) {
            $reply = 'Non hai il permesso per eseguire questa richiesta.';
        } else {
            // Nessuna chiamata Claude qui (match locale/verbo contestuale,
            // gratuito) - la frase viene da PromptConversationalInterface
            // se il tool la implementa, altrimenti resta vuota (il client
            // non mostra una bolla assistente senza testo, vedi hero.js).
            $reply = $tool instanceof PromptConversationalInterface ? ($tool->replySummary($input) ?? '') : '';
            if ($tool instanceof PromptConversationalInterface) {
                $suggestions = $tool->followUpSuggestions();
            }
        }

        $history[] = ['role' => 'user', 'content' => $message];
        $history[] = ['role' => 'assistant', 'content' => $reply];

        $this->json(['reply' => $reply, 'components' => $result['components'], 'suggestions' => $suggestions, 'history' => $history]);
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
