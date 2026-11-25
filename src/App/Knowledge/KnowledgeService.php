<?php

declare(strict_types=1);

namespace App\Knowledge;

use App\Core\Container;
use App\Model\CustomerNote;
use App\Model\CustomerProfile;
use App\Model\Task;
use App\Model\WorkOrder;
use App\Notifications\NotificationService;
use App\Notifications\NotificationTypes;
use App\Package\PackageCatalogService;
use App\Repository\CustomerNoteRepository;
use App\Repository\CustomerProfileRepository;
use App\Repository\CustomerTeamMemberRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderMemberRepository;
use App\Repository\WorkOrderRepository;
use App\Service\AiService;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\Work\TaskService;

/**
 * Quello che sappiamo di un cliente: il profilo (si riempie anche da solo
 * con il briefing) e il diario di idee e decisioni, con la roadmap interna.
 * Chi e' coinvolto in una nota (menzionato, o la squadra se la nota e'
 * condivisa) riceve un avviso.
 */
final class KnowledgeService
{
    /** campo => etichetta, nell'ordine della scheda */
    public const PROFILE_FIELDS = [
        'history' => 'Storia e attivita\'',
        'products' => 'Prodotti e servizi',
        'mission' => 'Mission e valori',
        'audience' => 'A chi si rivolgono',
        'competitors' => 'Concorrenti',
        'style_likes' => 'Stile: cosa piace',
        'style_dislikes' => 'Stile: cosa evitare',
        'tone_of_voice' => 'Tono di voce',
        'brand_usage' => 'Dove si usa il marchio',
        'seasonality' => 'Stagionalita\' ed eventi',
        'notes' => 'Altro da sapere',
    ];

    /**
     * Le domande del briefing e il campo del profilo che riempiono: parole
     * della domanda => campo. Senza IA basta questo; con l'IA le risposte
     * vengono anche sistemate e messe nei campi giusti.
     */
    private const QUESTION_MAP = [
        'storia' => 'history', 'attivita' => 'history',
        'prodott' => 'products', 'serviz' => 'products',
        'valori' => 'mission', 'mission' => 'mission',
        'pubblico' => 'audience', 'rivolge' => 'audience', 'target' => 'audience',
        'concorrent' => 'competitors', 'competitor' => 'competitors',
        'non piace' => 'style_dislikes', 'evitare' => 'style_dislikes',
        'piacciono' => 'style_likes', 'piace' => 'style_likes',
        'tono' => 'tone_of_voice',
        'dove si usera' => 'brand_usage', 'insegna' => 'brand_usage',
        'tempi' => 'notes', 'scadenz' => 'notes', 'materiale' => 'notes',
        'stagion' => 'seasonality', 'eventi' => 'seasonality', 'periodi' => 'seasonality',
    ];

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public static function isInstalled(): bool
    {
        return in_array('client_knowledge', PackageCatalogService::installedNames(), true);
    }

    public function profile(int $customerId): ?CustomerProfile
    {
        return self::isInstalled() ? $this->profiles()->findByCustomer($customerId) : null;
    }

    /**
     * Aggiunge al profilo: un campo vuoto si riempie, uno gia' scritto si
     * allunga con la novita' datata (mai si cancella quello che c'era).
     *
     * @param array<string,string> $fields campo => testo
     * @return int campi cambiati
     */
    public function mergeProfile(int $customerId, array $fields, string $source): int
    {
        $profile = $this->profiles()->findByCustomer($customerId);
        $data = [];
        foreach ($fields as $field => $text) {
            $text = trim((string) $text);
            if ($text === '' || !isset(self::PROFILE_FIELDS[$field])) {
                continue;
            }
            $current = $profile !== null ? trim((string) $profile->{lcfirst(str_replace('_', '', ucwords($field, '_')))}) : '';
            if ($current === '') {
                $data[$field] = $text;
            } elseif (!str_contains($current, $text)) {
                $data[$field] = $current . "\n\n" . date('d/m/Y') . " ({$source}): {$text}";
            }
        }
        if ($data === []) {
            return 0;
        }
        $data['last_source'] = mb_substr($source . ' del ' . date('d/m/Y'), 0, 60);
        if ($profile === null) {
            $this->profiles()->insert(['customer_id' => $customerId] + $data);
        } else {
            $this->profiles()->update((int) $profile->id, $data);
        }

        return count($data) - 1;
    }

    /**
     * Una nota nel diario del cliente (idea, decisione, preferenza...).
     * Avvisa i menzionati e, se $shareWithTeam, la squadra del cliente e
     * del progetto.
     *
     * @param array<string,mixed> $data campi di customer_notes
     */
    public function addNote(array $data, bool $shareWithTeam = false): int
    {
        $data += [
            'kind' => 'nota',
            'noted_at' => date('Y-m-d'),
            'source' => 'a mano',
            'author_user_id' => $this->container->get(AuthService::class)->currentUserId(),
        ];
        $id = $this->notes()->insert($data);
        $this->notifyNote($id, $shareWithTeam);

        return $id;
    }

    /**
     * @param ?int[] $onlyMentioned solo questi tra i menzionati (es. quelli appena aggiunti), null = tutti
     * @return int persone avvisate
     */
    public function notifyNote(int $noteId, bool $shareWithTeam, ?array $onlyMentioned = null): int
    {
        $note = $this->notes()->find($noteId);
        if (!$note instanceof CustomerNote) {
            return 0;
        }
        $people = $onlyMentioned ?? array_map('intval', array_filter(explode(',', (string) $note->mentionedUserIds)));
        if ($shareWithTeam) {
            $people = array_merge($people, $this->teamOf((int) $note->customerId, $note->workOrderId !== null ? (int) $note->workOrderId : null));
        }
        $author = $this->userNames()[(int) $note->authorUserId] ?? null;
        $customer = $this->container->get(\App\Repository\CustomerRepository::class)->find((int) $note->customerId);
        $title = ($note->onRoadmap ? 'Nella roadmap' : ucfirst((string) $note->kind)) . ' per ' . ($customer?->displayName() ?? 'un cliente') . ": {$note->title}";
        $notified = 0;
        foreach (array_unique($people) as $userId) {
            $notified += $this->container->get(NotificationService::class)->notify(
                $userId,
                NotificationTypes::MENTION,
                $title,
                trim(($author !== null ? "Da {$author}. " : '') . mb_substr((string) $note->body, 0, 300)),
                $note->workOrderId !== null ? '/commesse/' . $note->workOrderId : '/clienti/' . $note->customerId,
                'customer_notes',
                $noteId
            ) ? 1 : 0;
        }

        return $notified;
    }

    /** La squadra del cliente e dei suoi progetti aperti (o di quel progetto). @return int[] */
    public function teamOf(int $customerId, ?int $workOrderId): array
    {
        $people = [];
        $installed = PackageCatalogService::installedNames();
        if (in_array('customers', $installed, true)) {
            foreach ($this->container->get(CustomerTeamMemberRepository::class)->findByCustomer($customerId) as $member) {
                $people[] = (int) $member->userId;
            }
        }
        if (in_array('work_orders', $installed, true)) {
            $orders = $workOrderId !== null
                ? array_filter([$this->container->get(WorkOrderRepository::class)->find($workOrderId)])
                : array_filter($this->container->get(WorkOrderRepository::class)->findByCustomer($customerId), static fn (WorkOrder $o) => $o->isActive());
            foreach ($orders as $order) {
                foreach ($this->container->get(WorkOrderMemberRepository::class)->findByWorkOrder((int) $order->id) as $member) {
                    $people[] = (int) $member->userId;
                }
            }
        }

        return array_values(array_unique($people));
    }

    /** Domanda in piu' della raccolta informazioni, dopo la checklist. */
    public const INFO_EXTRA_QUESTION = 'Altro da sapere';

    /** Il tipo della fase del processo da cui nasce l'attivita' (briefing, raccolta informazioni...). */
    public function stepKind(Task $task): ?string
    {
        if ($task->sourceStepId === null || !in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $step = $this->container->get(\App\Repository\WorkOrderTypeStepRepository::class)->find((int) $task->sourceStepId);

        return $step?->kind;
    }

    /** Le voci della checklist della fase, senza il segno di fatto. @return string[] */
    private function checklistLines(Task $task): array
    {
        return array_values(array_filter(array_map(
            static fn (string $l) => trim(preg_replace('/^\[x\]\s*/i', '', $l)),
            explode("\n", (string) $task->checklist)
        )));
    }

    /**
     * @return string[] le domande: la checklist della fase; nella raccolta
     * informazioni anche "Altro da sapere", per quello che non era previsto.
     */
    public function briefingQuestions(Task $task): array
    {
        $questions = $this->checklistLines($task);
        if ($this->stepKind($task) === \App\Work\ProcessService::KIND_INFO) {
            $questions[] = self::INFO_EXTRA_QUESTION;
        }

        return $questions;
    }

    /**
     * Le risposte gia' date (raccolta informazioni salvata e da finire):
     * indice della domanda => risposta, per riempire di nuovo il modulo.
     *
     * @return array<int,string>
     */
    public function savedAnswers(Task $task): array
    {
        $noteKind = self::noteKindFor($this->stepKind($task));
        if ($task->entityType !== 'work_orders' || $noteKind === null) {
            return [];
        }
        $note = $this->notes()->findLatestForWorkOrder((int) $task->entityId, $noteKind);
        if (!$note instanceof CustomerNote) {
            return [];
        }
        $pairs = self::parsePairs((string) $note->body, $this->briefingQuestions($task));
        $answers = [];
        foreach ($this->briefingQuestions($task) as $i => $question) {
            if (isset($pairs[$question])) {
                $answers[$i] = $pairs[$question];
            }
        }

        return $answers;
    }

    /** Il tipo di nota del diario per le risposte di una fase guidata (null se non e' guidata). */
    public static function noteKindFor(?string $stepKind): ?string
    {
        return match ($stepKind) {
            \App\Work\ProcessService::KIND_INFO => self::NOTE_QUOTE_INFO,
            \App\Work\ProcessService::KIND_BRIEFING => 'briefing',
            default => null,
        };
    }

    /**
     * Per la scheda "Da fare" di un'attivita' guidata (briefing o raccolta
     * informazioni): le domande (le voci della checklist, poi le domande in
     * piu'), le risposte gia' salvate, il pulsante che chiude, la guida.
     * null se la fase non e' guidata.
     */
    public function guidedTaskData(Task $task): ?array
    {
        $kind = $this->stepKind($task);
        if (self::noteKindFor($kind) === null || $task->entityType !== 'work_orders') {
            return null;
        }
        $isInfo = $kind === \App\Work\ProcessService::KIND_INFO;
        $order = $this->container->get(WorkOrderRepository::class)->find((int) $task->entityId);
        $known = !$isInfo && $order?->customerId !== null ? $this->forCustomer((int) $order->customerId)['profile'] : [];

        return [
            'questions' => $this->briefingQuestions($task),
            'checklistCount' => count($this->checklistLines($task)),
            'answers' => $this->savedAnswers($task),
            'known' => $known,
            'completeLabel' => $isInfo ? 'Informazioni raccolte' : 'Briefing fatto',
            'answerPlaceholder' => $isInfo ? 'Cosa ha detto il cliente' : 'La risposta del cliente',
            'hint' => $isInfo
                ? "Le risposte si salvano da sole e restano nel progetto: chi prepara il preventivo le trova nella sua fase."
                : "Le risposte si salvano da sole nel diario; chiudendo il briefing vanno anche nel profilo del cliente.",
            'guide' => $isInfo
                ? [
                    'text' => "Ogni voce e' una domanda da fare al cliente. Scrivi la risposta sotto la voce: si salva da sola, e la voce si spunta. Puoi spuntare anche senza scrivere niente.",
                    'steps' => ['Durante o dopo l\'incontro, scrivi le risposte.', '"Altro da sapere" e\' per quello che non era tra le domande.', '"Informazioni raccolte" chiude la fase: tocca a chi prepara il preventivo, che riceve l\'avviso.'],
                ]
                : [
                    'text' => "Ogni voce e' una domanda del briefing. Scrivi la risposta sotto: si salva da sola nel diario del progetto.",
                    'tip' => '"Briefing fatto" chiude la fase e porta le risposte nel profilo del cliente, che usano la squadra e l\'assistente.',
                ],
        ];
    }

    /**
     * Le risposte scritte finora (salvataggio automatico): solo la nota
     * del progetto, senza toccare checklist, profilo o stato della fase.
     *
     * @param array<int,string> $answers
     */
    public function saveAnswers(int $taskId, array $answers): bool
    {
        $task = $this->container->get(TaskRepository::class)->find($taskId);
        if (!$task instanceof Task || $task->entityType !== 'work_orders') {
            return false;
        }
        $order = $this->container->get(WorkOrderRepository::class)->find((int) $task->entityId);
        $noteKind = self::noteKindFor($this->stepKind($task));
        if (!$order instanceof WorkOrder || $order->customerId === null || $noteKind === null) {
            return false;
        }
        $this->upsertAnswersNote($task, $order, $noteKind, $this->pairs($task, $answers));

        return true;
    }

    /** @param array<int,string> $answers @return array<string,string> domanda => risposta, solo quelle scritte */
    private function pairs(Task $task, array $answers): array
    {
        $pairs = [];
        foreach ($this->briefingQuestions($task) as $i => $question) {
            $answer = trim((string) ($answers[$i] ?? ''));
            if ($answer !== '') {
                $pairs[$question] = $answer;
            }
        }

        return $pairs;
    }

    /** Una sola nota per progetto e tipo, aggiornata a ogni salvataggio. @param array<string,string> $pairs */
    private function upsertAnswersNote(Task $task, WorkOrder $order, string $noteKind, array $pairs): void
    {
        $body = implode("\n\n", array_map(static fn (string $q, string $a) => "{$q}\n{$a}", array_keys($pairs), $pairs));
        $existing = $this->notes()->findLatestForWorkOrder((int) $order->id, $noteKind);
        if ($existing instanceof CustomerNote) {
            if ((string) $existing->body !== $body) {
                $this->notes()->update((int) $existing->id, ['body' => $body, 'noted_at' => date('Y-m-d'), 'author_user_id' => $this->container->get(AuthService::class)->currentUserId()]);
            }

            return;
        }
        if ($pairs === []) {
            return;
        }
        $isInfo = $noteKind === self::NOTE_QUOTE_INFO;
        $this->addNote([
            'customer_id' => $order->customerId,
            'work_order_id' => $order->id,
            'kind' => $noteKind,
            'title' => ($isInfo ? 'Informazioni per il preventivo: ' : 'Briefing: ') . $order->title,
            'body' => $body,
            'source' => $isInfo ? 'raccolta informazioni' : 'briefing',
        ], !$isInfo);
    }

    /** Il tipo di nota con le informazioni per il preventivo. */
    public const NOTE_QUOTE_INFO = 'preventivo';

    /**
     * Il briefing (o la raccolta informazioni) compilato.
     * - Briefing: le risposte nel diario (una nota "briefing") e nel
     *   profilo del cliente, campo per campo.
     * - Raccolta informazioni: le risposte in una nota del progetto
     *   (sempre la stessa, aggiornata), che vede chi prepara il preventivo.
     * In tutti e due i casi le voci con una risposta si spuntano, e con
     * $complete la fase e' fatta.
     *
     * @param array<int,string> $answers indice della domanda => risposta
     * @return string cosa e' successo
     */
    public function saveBriefing(int $taskId, array $answers, bool $complete): string
    {
        $task = $this->container->get(TaskRepository::class)->find($taskId);
        if (!$task instanceof Task || $task->entityType !== 'work_orders') {
            return 'Fase non trovata.';
        }
        $order = $this->container->get(WorkOrderRepository::class)->find((int) $task->entityId);
        if (!$order instanceof WorkOrder || $order->customerId === null) {
            return 'Serve un cliente.';
        }
        $isInfo = $this->stepKind($task) === \App\Work\ProcessService::KIND_INFO;
        $pairs = $this->pairs($task, $answers);
        if ($pairs === [] && !$complete) {
            return 'Nessuna risposta da salvare.';
        }

        $this->tickAnswered($task, array_keys($pairs));
        $this->upsertAnswersNote($task, $order, $isInfo ? self::NOTE_QUOTE_INFO : 'briefing', $pairs);

        $changed = 0;
        if (!$isInfo && $pairs !== [] && $complete) {
            // Il profilo del cliente si aggiorna quando il briefing e' fatto.
            $fields = $this->fieldsFromAi($pairs) ?? $this->fieldsFromMap($pairs);
            $changed = $this->mergeProfile((int) $order->customerId, $fields, 'briefing');
        }
        if ($complete && $task->isOpen()) {
            $this->container->get(TaskService::class)->complete($taskId);
        }

        if ($isInfo) {
            return 'Informazioni salvate nel progetto: ' . F::plural(count($pairs), 'risposta', 'risposte')
                . ($complete ? ", fase fatta. Chi prepara il preventivo le trova nella sua fase." : '. Puoi finire dopo.');
        }

        return 'Briefing salvato: ' . F::plural(count($pairs), 'risposta', 'risposte') . ' nel diario'
            . ($complete ? ', ' . F::plural($changed, 'voce', 'voci') . ' del profilo del cliente aggiornate, fase fatta.' : '.');
    }

    /** Spunta le voci della checklist che hanno una risposta. @param string[] $answered */
    private function tickAnswered(Task $task, array $answered): void
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $task->checklist)), static fn (string $l) => $l !== ''));
        if ($lines === []) {
            return;
        }
        $changed = false;
        foreach ($lines as $i => $line) {
            $text = trim((string) preg_replace('/^\[x\]\s*/i', '', $line));
            if (in_array($text, $answered, true) && $text === $line) {
                $lines[$i] = '[x] ' . $text;
                $changed = true;
            }
        }
        if ($changed) {
            $this->container->get(TaskRepository::class)->update((int) $task->id, ['checklist' => implode("\n", $lines)]);
        }
    }

    /**
     * Domanda => risposta da una nota scritta come "domanda\nrisposta\n\n...".
     * Si riconoscono le domande note, cosi' una risposta puo' avere righe vuote.
     *
     * @param string[] $questions
     * @return array<string,string>
     */
    public static function parsePairs(string $body, array $questions): array
    {
        $pairs = [];
        $current = null;
        $buffer = [];
        foreach (explode("\n", str_replace("\r", '', $body)) as $line) {
            if (in_array(trim($line), $questions, true)) {
                if ($current !== null) {
                    $pairs[$current] = trim(implode("\n", $buffer));
                }
                $current = trim($line);
                $buffer = [];
                continue;
            }
            $buffer[] = $line;
        }
        if ($current !== null) {
            $pairs[$current] = trim(implode("\n", $buffer));
        }

        return array_filter($pairs, static fn (string $a) => $a !== '');
    }

    /**
     * Le informazioni raccolte per il preventivo di un progetto, per chi lo
     * prepara: nella sua fase e nella pagina del preventivo. null se non ci sono.
     */
    public function quoteBrief(int $workOrderId): ?array
    {
        $note = $this->notes()->findLatestForWorkOrder($workOrderId, self::NOTE_QUOTE_INFO);
        $order = $this->container->get(WorkOrderRepository::class)->find($workOrderId);
        if (!$note instanceof CustomerNote || !$order instanceof WorkOrder) {
            return null;
        }
        // Le domande della fase di raccolta di questo progetto; se la fase
        // non c'e' piu', la prima riga di ogni blocco (vedi saveBriefing).
        $questions = [];
        foreach ($this->container->get(TaskRepository::class)->findForEntity('work_orders', $workOrderId) as $task) {
            if ($this->stepKind($task) === \App\Work\ProcessService::KIND_INFO) {
                $questions = array_merge($questions, $this->briefingQuestions($task));
            }
        }
        if ($questions === []) {
            $blocks = preg_split("/\n\s*\n/", trim((string) $note->body)) ?: [];
            $questions = array_filter(array_map(static fn (string $b) => trim(strtok($b, "\n") ?: ''), $blocks));
        }
        $items = [];
        foreach (self::parsePairs((string) $note->body, array_values(array_unique($questions))) as $q => $a) {
            $items[] = ['label' => $q, 'text' => $a];
        }
        $names = $this->userNames();

        return [
            'type' => 'quote-brief',
            'title' => 'Cosa ha chiesto il cliente',
            'subtitle' => trim('Raccolto ' . ($note->authorUserId !== null && isset($names[(int) $note->authorUserId]) ? 'da ' . $names[(int) $note->authorUserId] . ' ' : '') . F::relativeDays((string) $note->notedAt, date('Y-m-d'))),
            'items' => $items,
            'project' => ['label' => (string) $order->title, 'href' => '/commesse/' . $order->id],
            'guide' => [
                'text' => "Le informazioni raccolte all'incontro con il cliente (fase \"Raccogli le informazioni\"): servono per preparare il preventivo.",
                'tip' => 'Le trovi anche nel diario del progetto.',
            ],
        ];
    }

    /** @param array<string,string> $pairs @return array<string,string> */
    private function fieldsFromMap(array $pairs): array
    {
        $fields = [];
        foreach ($pairs as $question => $answer) {
            $q = mb_strtolower($question);
            $q = strtr($q, ["'" => '', 'à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
            $field = 'notes';
            foreach (self::QUESTION_MAP as $word => $candidate) {
                if (str_contains($q, $word)) {
                    $field = $candidate;
                    break;
                }
            }
            $fields[$field] = trim(($fields[$field] ?? '') . "\n" . ($field === 'notes' ? "{$question}: {$answer}" : $answer));
        }

        return $fields;
    }

    /** @param array<string,string> $pairs @return ?array<string,string> */
    private function fieldsFromAi(array $pairs): ?array
    {
        $ai = $this->container->get(AiService::class);
        if (!$ai->isConfigured()) {
            return null;
        }
        try {
            $answer = $ai->json(
                "Sei l'assistente di un'agenzia di comunicazione. Dalle risposte di un briefing con un cliente compila il suo profilo. "
                . 'Rispondi con un oggetto JSON con solo queste chiavi (stringhe in italiano, sintetiche e fedeli a quanto detto, vuote se non c\'e\' nulla): '
                . implode(', ', array_keys(self::PROFILE_FIELDS)) . '.',
                implode("\n\n", array_map(static fn (string $q, string $a) => "Domanda: {$q}\nRisposta: {$a}", array_keys($pairs), $pairs)),
                1500
            );
            $fields = array_filter(array_intersect_key(array_map('strval', $answer), self::PROFILE_FIELDS), static fn (string $v) => trim($v) !== '');

            return $fields !== [] ? $fields : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Un'idea della roadmap e' diventata un progetto: collegata, "in corso". */
    public function linkIdeaToProject(int $noteId, int $workOrderId): void
    {
        $this->notes()->update($noteId, ['work_order_id' => $workOrderId, 'roadmap_status' => 'in corso']);
    }

    /**
     * Il progetto nato da un'idea cambia: l'idea lo segue (consegnato ->
     * "fatta", non acquisito -> "scartata").
     */
    public function syncRoadmap(int $workOrderId, string $status): int
    {
        if (!self::isInstalled()) {
            return 0;
        }
        $notes = $this->notes()->openRoadmapForWorkOrder($workOrderId);
        foreach ($notes as $note) {
            $this->notes()->update((int) $note->id, ['roadmap_status' => $status]);
        }

        return count($notes);
    }

    /**
     * Per la scheda (cliente o progetto): il profilo, la roadmap e il diario.
     */
    public function forCustomer(int $customerId, ?int $workOrderId = null): array
    {
        $profile = $this->profiles()->findByCustomer($customerId);
        $filled = [];
        if ($profile !== null) {
            foreach (self::PROFILE_FIELDS as $field => $label) {
                $value = trim((string) $profile->{lcfirst(str_replace('_', '', ucwords($field, '_')))});
                if ($value !== '') {
                    $filled[] = ['label' => $label, 'text' => $value];
                }
            }
        }
        $today = date('Y-m-d');
        $names = $this->userNames();
        $notes = $this->notes()->findByCustomer($customerId, 60);
        if ($workOrderId !== null) {
            $notes = array_values(array_filter($notes, static fn (CustomerNote $n) => $n->workOrderId === null || (int) $n->workOrderId === $workOrderId));
        }
        $row = static fn (CustomerNote $n) => [
            'title' => (string) $n->title,
            'body' => $n->body,
            'kind' => (string) $n->kind,
            'status' => $n->roadmapStatus,
            'due' => $n->dueDate !== null ? F::shortDate((string) $n->dueDate, $today) : null,
            'when' => F::relativeDays((string) $n->notedAt, $today),
            'who' => $n->authorUserId !== null ? ($names[(int) $n->authorUserId] ?? null) : null,
            'people' => array_values(array_filter(array_map(static fn ($id) => $names[(int) $id] ?? null, array_filter(explode(',', (string) $n->mentionedUserIds))))),
            'href' => '/clienti/diario/' . $n->id,
        ];
        // Le idee della roadmap: il progetto in cui sono diventate, o il pulsante per farlo.
        $orders = in_array('work_orders', PackageCatalogService::installedNames(), true) ? $this->container->get(WorkOrderRepository::class) : null;
        $canCreate = $orders !== null && $this->container->get(AuthService::class)->hasPermission('work_orders.create');
        $ideaRow = function (CustomerNote $n) use ($row, $orders, $canCreate): array {
            $data = $row($n);
            $order = $orders !== null && $n->workOrderId !== null ? $orders->find((int) $n->workOrderId) : null;
            if ($order instanceof WorkOrder && $order->customerId !== null && (int) $order->customerId === (int) $n->customerId && $n->roadmapStatus !== 'da valutare') {
                $data['project'] = ['label' => (string) $order->title, 'href' => '/commesse/' . $order->id, 'stage' => (string) $order->stage];
            } elseif ($canCreate && !in_array($n->roadmapStatus, ['fatta', 'scartata'], true)) {
                $data['toProject'] = '/clienti/diario/' . $n->id . '/progetto';
            }

            return $data;
        };
        $roadmap = array_values(array_filter($notes, static fn (CustomerNote $n) => (int) $n->onRoadmap === 1 && $n->roadmapStatus !== 'scartata'));
        $diary = array_values(array_filter($notes, static fn (CustomerNote $n) => (int) $n->onRoadmap !== 1));

        $query = 'customer_id=' . $customerId . ($workOrderId !== null ? '&work_order_id=' . $workOrderId : '');

        return [
            'type' => 'customer-knowledge',
            'profile' => $filled,
            'profileHref' => $profile !== null ? '/clienti/profilo/' . $profile->id : '/clienti/profilo/nuovo?customer_id=' . $customerId,
            'profileUpdated' => $profile?->lastSource,
            'roadmap' => array_map($ideaRow, $roadmap),
            // Per "Diventa un progetto": i processi tra cui scegliere.
            'projectTypes' => $canCreate ? array_values(array_map(static fn ($t) => ['id' => (int) $t->id, 'name' => (string) $t->name], array_filter(
                $this->container->get(\App\Repository\WorkOrderTypeRepository::class)->findAll([], 'name'),
                static fn ($t) => $t->stage !== 'non in uso'
            ))) : [],
            'diary' => array_map($row, array_slice($diary, 0, 12)),
            'addIdeaHref' => '/clienti/diario/nuovo?' . $query . '&kind=idea&on_roadmap=1&roadmap_status=' . rawurlencode('da valutare'),
            'addNoteHref' => '/clienti/diario/nuovo?' . $query . '&kind=nota',
            'scope' => $workOrderId !== null ? 'progetto' : 'cliente',
        ];
    }

    /** @return array<int,string> */
    private function userNames(): array
    {
        $names = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $names[(int) $user->id] = trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username;
        }

        return $names;
    }

    private function profiles(): CustomerProfileRepository
    {
        return $this->container->get(CustomerProfileRepository::class);
    }

    private function notes(): CustomerNoteRepository
    {
        return $this->container->get(CustomerNoteRepository::class);
    }
}
