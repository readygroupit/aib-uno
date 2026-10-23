<?php

declare(strict_types=1);

namespace App\Editorial;

use App\Core\Container;
use App\Model\Customer;
use App\Model\EditorialPost;
use App\Model\Task;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\CustomerTeamMemberRepository;
use App\Repository\EditorialStrategyRepository;
use App\Repository\UserRepository;
use App\Repository\EditorialPostRepository;
use App\Repository\EditorialQuotaRepository;
use App\Repository\TaskRepository;
use App\Service\AiService;
use App\Work\TaskService;
use App\Work\Workdays;

/**
 * Cosa succede a un contenuto senza doverci pensare:
 * - due mani: chi scrive il testo (di solito il social media manager) e chi
 *   lo realizza (grafica o video). Ognuno trova la sua attivita'
 *   nell'organizer: il testo 4 giorni lavorativi prima dell'uscita, la
 *   realizzazione 2 giorni prima, con il tempo tipico del formato. Se la
 *   persona e' la stessa, l'attivita' e' una sola;
 * - il passo successivo (advance()): pronto -> revisione interna (o dritto
 *   al cliente se chi lo segna puo' approvare), approvazione interna e del
 *   cliente registrate a parte con chi, quando e come; "da modificare"
 *   conta i giri di modifiche e riapre il lavoro per oggi;
 * - mancano contenuti rispetto al contratto: si creano le bozze, distribuite
 *   nei giorni, assegnate alla squadra del cliente (o proposte dall'IA dalla
 *   strategia del cliente);
 * - dal post nasce la storia (deriveStory()); il testo lo puo' proporre l'IA.
 */
final class EditorialService
{
    public const ENTITY = 'editorial_posts';
    public const FORMAT_MINUTES = ['post' => 45, 'carosello' => 90, 'reel' => 180, 'storia' => 20, 'video' => 240];
    /** Minuti per scrivere il testo, per formato. */
    public const WRITING_MINUTES = ['post' => 15, 'carosello' => 25, 'reel' => 20, 'storia' => 10, 'video' => 25];
    public const VIDEO_FORMATS = ['reel', 'video'];
    /** Pronto: da qui in poi il lavoro di testo e realizzazione e' fatto. */
    private const READY_STAGES = ['revisione interna', 'da approvare', 'approvato', 'programmato', 'pubblicato'];

    private EditorialPostRepository $posts;
    private EditorialQuotaRepository $quotas;
    private TaskRepository $tasks;
    private TaskService $taskService;
    private CustomerRepository $customers;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->posts = $container->get(EditorialPostRepository::class);
        $this->quotas = $container->get(EditorialQuotaRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
        $this->taskService = $container->get(TaskService::class);
        $this->customers = $container->get(CustomerRepository::class);
    }

    /**
     * Il passo successivo di un contenuto. 'pronto' e' il passo "ho finito":
     * va in revisione interna, o dritto al cliente se chi lo segna puo'
     * approvare (la sua e' gia' l'approvazione interna).
     *
     * @return string cosa e' successo, per l'operatore
     */
    public function advance(int $postId, string $stage, int $userId, bool $canApprove, string $note = '', ?string $via = null): string
    {
        $post = $this->posts->find($postId);
        if (!$post instanceof EditorialPost) {
            throw new \InvalidArgumentException('Contenuto non trovato.');
        }
        $now = date('Y-m-d H:i:s');
        $note = trim($note);
        $data = [];

        if ($stage === 'pronto') {
            $stage = $canApprove ? 'da approvare' : 'revisione interna';
        }
        switch ($stage) {
            case 'da approvare':
                if ($post->internalApprovedAt === null || $post->stage === 'revisione interna' || $post->stage === 'da modificare') {
                    if (!$canApprove) {
                        throw new \InvalidArgumentException('Serve l\'approvazione interna di chi puo\' approvare.');
                    }
                    $data += ['internal_approved_by' => $userId, 'internal_approved_at' => $now];
                }
                $message = 'Approvato internamente: ora lo vede il cliente nella sua area.';
                break;
            case 'revisione interna':
                $message = 'Pronto: aspetta la revisione interna.';
                break;
            case 'da modificare':
                $data['revision_count'] = (int) $post->revisionCount + 1;
                // Dalla revisione interna e' una nota nostra, dopo e' il cliente che chiede.
                if ($note !== '') {
                    $data[$post->stage === 'revisione interna' ? 'review_note' : 'client_feedback'] = $note;
                }
                $message = 'Da modificare: torna a chi lo prepara, per oggi.';
                break;
            case 'approvato':
                // Come l'ha approvato (WhatsApp, a voce...) e chi l'ha segnato; dall'area cliente lo dice da se'.
                $data += ['approved_at' => $now, 'approved_via' => mb_substr($via === 'area cliente' ? $via : ($via ? $via . ', ' : '') . $this->userName($userId, 'segnato da '), 0, 60)];
                if ($post->internalApprovedAt === null && $canApprove) {
                    $data += ['internal_approved_by' => $userId, 'internal_approved_at' => $now];
                }
                $message = 'Approvato dal cliente: si puo\' programmare.';
                break;
            case 'in lavorazione':
                $message = 'In lavorazione.';
                break;
            case 'programmato':
                $message = 'Programmato.';
                break;
            case 'pubblicato':
                if ($note !== '' && preg_match('#^https?://#', $note) === 1) {
                    $data['published_url'] = $note;
                }
                $message = 'Pubblicato.';
                break;
            case 'scartato':
                $message = 'Scartato: annullate anche le attivita\'.';
                break;
            default:
                throw new \InvalidArgumentException('Stato non valido.');
        }

        $this->posts->update($postId, ['stage' => $stage] + $data);
        $this->syncTask($postId);

        return $message;
    }

    /**
     * Approvazione interna di tutto cio' che di un cliente aspetta la
     * revisione (il piano del mese in un colpo).
     *
     * @return int contenuti approvati
     */
    public function approveAllInternally(int $customerId, int $userId): int
    {
        $count = 0;
        foreach ($this->posts->findForCustomerInStage($customerId, 'revisione interna') as $post) {
            $this->advance((int) $post->id, 'da approvare', $userId, true);
            $count++;
        }

        return $count;
    }

    /** Il cliente approva tutto quello che aspetta lui (area cliente). */
    public function approveAllByClient(int $customerId): int
    {
        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($this->posts->findForCustomerInStage($customerId, 'da approvare') as $post) {
            $this->posts->update((int) $post->id, ['stage' => 'approvato', 'approved_at' => $now, 'approved_via' => 'area cliente']);
            $this->syncTask((int) $post->id);
            $count++;
        }

        return $count;
    }

    /** La storia che accompagna un post: stesso giorno, stesso testo da adattare, stesse persone. */
    public function deriveStory(int $postId): int
    {
        $post = $this->posts->find($postId);
        if (!$post instanceof EditorialPost) {
            throw new \InvalidArgumentException('Contenuto non trovato.');
        }
        if ($post->format === 'storia') {
            throw new \InvalidArgumentException('E\' gia\' una storia.');
        }
        $id = $this->posts->insert([
            'customer_id' => $post->customerId,
            'channel' => $post->channel === 'LinkedIn' ? 'Instagram' : $post->channel,
            'format' => 'storia',
            'publish_date' => $post->publishDate,
            'publish_time' => $post->publishTime,
            'title' => 'Storia: ' . $post->title,
            'caption' => $post->caption,
            'media_notes' => 'Dal post: formato 9:16, testi brevi sull\'immagine',
            'source_link' => $post->outputLink ?: $post->sourceLink,
            'stage' => 'in lavorazione',
            'writer_user_id' => $post->writerUserId,
            'assigned_user_id' => $post->assignedUserId,
            'parent_post_id' => $post->id,
        ]);
        $this->syncTask($id);

        return $id;
    }

    /**
     * Chi scrive e chi realizza, quando non e' indicato: dalla squadra del
     * cliente (social; video per reel e video, grafica per il resto), poi
     * da chi di solito prepara i contenuti di quel contratto.
     *
     * @return array{writer:?int, producer:?int}
     */
    public function defaultPeople(int $customerId, ?string $channel, ?string $format): array
    {
        $team = [];
        if (in_array('customers', PackageCatalogService::installedNames(), true)) {
            foreach ($this->container->get(CustomerTeamMemberRepository::class)->findByCustomer($customerId) as $member) {
                $team[(string) $member->role] ??= (int) $member->userId;
            }
        }
        $quotaUser = null;
        foreach ($this->quotas->findAll(['customer_id' => $customerId]) as $quota) {
            if ($quota->defaultUserId !== null && ($quotaUser === null || $quota->channel === $channel)) {
                $quotaUser = (int) $quota->defaultUserId;
            }
        }
        $writer = $team['social'] ?? $quotaUser;
        $producer = in_array($format, self::VIDEO_FORMATS, true) ? ($team['video'] ?? $team['grafica'] ?? null) : ($team['grafica'] ?? null);

        return ['writer' => $writer, 'producer' => $producer ?? $quotaUser ?? $writer];
    }

    /** Riempie chi scrive e chi realizza se mancano (dopo una creazione). */
    public function fillPeople(int $postId): void
    {
        $post = $this->posts->find($postId);
        if (!$post instanceof EditorialPost || ($post->writerUserId !== null && $post->assignedUserId !== null)) {
            return;
        }
        $people = $this->defaultPeople((int) $post->customerId, $post->channel, $post->format);
        $this->posts->update($postId, array_filter([
            'writer_user_id' => $post->writerUserId === null ? $people['writer'] : null,
            'assigned_user_id' => $post->assignedUserId === null ? $people['producer'] : null,
        ]));
    }

    /** Allinea le attivita' (testo e realizzazione) al contenuto, dopo ogni salvataggio. */
    public function syncTask(int $postId): void
    {
        $post = $this->posts->find($postId, true);
        if (!$post instanceof EditorialPost) {
            return;
        }
        $producerTask = $post->taskId !== null ? $this->tasks->find((int) $post->taskId) : null;
        $writerTask = $post->writerTaskId !== null ? $this->tasks->find((int) $post->writerTaskId) : null;

        // Scartato o eliminato: le attivita' aperte si annullano.
        if ($post->status === 0 || $post->stage === 'scartato') {
            foreach ([$producerTask, $writerTask] as $task) {
                if ($task instanceof Task && $task->isOpen()) {
                    $this->tasks->update((int) $task->id, ['stage' => 'annullata']);
                }
            }

            return;
        }
        // Pronto o oltre: il lavoro e' fatto.
        if (in_array($post->stage, self::READY_STAGES, true)) {
            foreach ([$writerTask, $producerTask] as $task) {
                if ($task instanceof Task && $task->isOpen()) {
                    $this->taskService->complete((int) $task->id);
                }
            }

            return;
        }

        $customer = $this->customers->find((int) $post->customerId);
        $who = $customer instanceof Customer ? ' (' . $customer->displayName() . ')' : '';
        $format = (string) ($post->format ?? 'contenuto');
        $fix = $post->stage === 'da modificare';
        // Con una sola persona (o senza chi scrive) l'attivita' e' una: testo e realizzazione insieme.
        $separateWriter = $post->writerUserId !== null && $post->assignedUserId !== null && $post->writerUserId !== $post->assignedUserId;
        $producerId = $post->assignedUserId ?? $post->writerUserId;
        if ($producerId === null) {
            return;
        }

        $checklist = array_filter([
            $post->mediaNotes,
            $post->sourceLink ? 'Materiale: ' . $post->sourceLink : null,
            in_array($format, self::VIDEO_FORMATS, true) ? "Copertina dal video\nSottotitoli controllati" : null,
            $fix ? 'Da correggere: ' . ($post->clientFeedback ?: $post->reviewNote) : null,
        ]);
        $producerData = [
            'title' => ($fix ? 'Correggere ' : ($separateWriter ? 'Realizzare ' : 'Preparare ')) . "{$format} {$post->channel}: {$post->title}{$who}",
            'planned_date' => $fix ? Workdays::adjust(date('Y-m-d')) : self::workdaysBefore((string) $post->publishDate, 2),
            'due_at' => $post->publishDate . ' ' . ($post->publishTime ?: '09:00') . ':00',
            // Una correzione e' un pezzo del lavoro, non il lavoro intero.
            'estimated_minutes' => $fix
                ? max(30, (int) round((self::FORMAT_MINUTES[$format] ?? 45) * 0.35 / 15) * 15)
                : (self::FORMAT_MINUTES[$format] ?? 45) + ($separateWriter ? 0 : (self::WRITING_MINUTES[$format] ?? 15)),
            'assigned_user_id' => $producerId,
            'checklist' => implode("\n", $checklist) ?: null,
            'priority' => $fix ? 'alta' : 'normale',
            'description' => $fix ? ($post->clientFeedback ?: $post->reviewNote) : null,
        ];
        // Un'idea con chi scrive a parte: prima il tema e il testo; la realizzazione parte quando e' "in lavorazione".
        if (!($separateWriter && $post->stage === 'idea' && !$producerTask instanceof Task)) {
            $this->upsertTask($post, $producerTask, $producerData, 'task_id', $fix);
        }

        if ($separateWriter) {
            $writerData = [
                'title' => ($post->stage === 'idea' ? 'Tema e testo ' : 'Testo ') . "{$format} {$post->channel}: {$post->title}{$who}",
                'planned_date' => self::workdaysBefore((string) $post->publishDate, 4),
                'due_at' => self::workdaysBefore((string) $post->publishDate, 2) . ' 18:00:00',
                'estimated_minutes' => self::WRITING_MINUTES[$format] ?? 15,
                'assigned_user_id' => $post->writerUserId,
                'priority' => 'normale',
            ];
            // Il testo si riapre solo se la modifica chiesta lo riguarda: lo decide chi corregge.
            $this->upsertTask($post, $writerTask, $writerData, 'writer_task_id', false);
        } elseif ($writerTask instanceof Task && $writerTask->isOpen()) {
            $this->tasks->update((int) $writerTask->id, ['stage' => 'annullata']);
        }
    }

    private function upsertTask(EditorialPost $post, ?Task $task, array $data, string $column, bool $reopen): void
    {
        if ($task instanceof Task) {
            if (!$task->isOpen() && !$reopen) {
                // Gia' fatta: resta fatta, si aggiorna solo chi e il titolo.
                $this->tasks->update((int) $task->id, ['title' => $data['title'], 'assigned_user_id' => $data['assigned_user_id']]);

                return;
            }
            if (!$task->isOpen()) {
                $data += ['stage' => 'da fare', 'completed_at' => null];
            }
            $this->tasks->update((int) $task->id, $data);

            return;
        }
        $taskId = $this->tasks->insert($data + [
            'stage' => 'da fare',
            'waits_for_client' => 0,
            'entity_type' => self::ENTITY,
            'entity_id' => $post->id,
        ]);
        $this->posts->update((int) $post->id, [$column => $taskId]);
    }

    private function userName(int $userId, string $prefix = ''): string
    {
        $user = $this->container->get(UserRepository::class)->find($userId);

        return $prefix . ($user !== null ? trim("{$user->firstName} {$user->lastName}") : 'agenzia');
    }

    /**
     * Le bozze che mancano per rispettare il contratto nelle prossime due
     * settimane, distribuite nei giorni lavorativi e assegnate alla squadra
     * del cliente. Con $withAi e una strategia del cliente, l'IA propone
     * tema, testo e materiale di ogni bozza (dalle rubriche, senza ripetere
     * i temi recenti); se non risponde restano "Da definire".
     *
     * @return int bozze create
     */
    public function fillGaps(int $customerId, string $channel, string $from, bool $withAi = false): int
    {
        $quota = null;
        foreach ($this->quotas->findAll(['customer_id' => $customerId, 'channel' => $channel]) as $candidate) {
            $quota = $candidate;
        }
        if ($quota === null) {
            return 0;
        }

        $to = date('Y-m-d', strtotime("{$from} +13 days"));
        $existing = $this->posts->countPerCustomerChannel($from, $to)["{$customerId}|{$channel}"] ?? 0;
        $missing = max(0, 2 * (int) $quota->perWeek - $existing);
        if ($missing === 0) {
            return 0;
        }

        // Giorni lavorativi dei 14, gia' occupati da un contenuto di questo cliente e canale esclusi.
        $taken = [];
        foreach ($this->posts->findBetween($from, $to) as $post) {
            if ((int) $post->customerId === $customerId && $post->channel === $channel) {
                $taken[(string) $post->publishDate] = true;
            }
        }
        $days = [];
        for ($i = 1; $i <= 13; $i++) {
            $day = date('Y-m-d', strtotime("{$from} +{$i} days"));
            if ((int) date('N', strtotime($day)) <= 5 && !isset($taken[$day])) {
                $days[] = $day;
            }
        }
        if ($days === []) {
            return 0;
        }

        $formats = self::formatsFromText((string) $quota->formats);
        $step = count($days) / $missing;
        $slots = [];
        for ($n = 0; $n < $missing; $n++) {
            $slots[] = ['date' => $days[min(count($days) - 1, (int) floor($n * $step))], 'format' => $formats[$n % count($formats)]];
        }
        $ideas = [];
        if ($withAi) {
            try {
                $ideas = $this->proposeIdeas($customerId, $channel, $slots);
            } catch (\Throwable) {
                $ideas = [];
            }
        }

        $created = 0;
        foreach ($slots as $n => $slot) {
            $people = $this->defaultPeople($customerId, $channel, $slot['format']);
            $idea = $ideas[$n] ?? null;
            $id = $this->posts->insert([
                'customer_id' => $customerId,
                'channel' => $channel,
                'format' => $slot['format'],
                'publish_date' => $slot['date'],
                'publish_time' => '12:30',
                'title' => mb_substr(trim((string) ($idea['title'] ?? '')) ?: 'Da definire', 0, 200),
                'caption' => isset($idea['caption']) ? trim((string) $idea['caption']) : null,
                'media_notes' => isset($idea['media']) ? mb_substr(trim((string) $idea['media']), 0, 255) : null,
                'stage' => 'idea',
                'writer_user_id' => $people['writer'],
                'assigned_user_id' => $people['producer'],
            ]);
            $this->syncTask($id);
            $created++;
        }

        return $created;
    }

    /**
     * Temi proposti dall'IA per le date indicate, dalla strategia del
     * cliente. Lancia se manca la strategia o l'IA non risponde.
     *
     * @param list<array{date:string, format:string}> $slots
     * @return list<array{title:string, caption:?string, media:?string}>
     */
    public function proposeIdeas(int $customerId, string $channel, array $slots): array
    {
        $strategy = $this->container->get(EditorialStrategyRepository::class)->findByCustomer($customerId);
        if ($strategy === null) {
            throw new \RuntimeException('Manca la strategia del cliente.');
        }
        $customer = $this->customers->find($customerId);
        $recent = array_map(
            static fn (EditorialPost $p) => (string) $p->title,
            array_slice($this->posts->findAll(['customer_id' => $customerId], 'publish_date DESC'), 0, 30)
        );

        $system = "Sei il social media manager di un'agenzia di comunicazione italiana. Proponi i contenuti del piano editoriale "
            . 'di un cliente seguendo la sua strategia: un tema concreto per ogni data (usa le rubriche, alternale, niente temi gia\' usati), '
            . 'una bozza di testo breve con invito all\'azione e 3-5 hashtag, e cosa serve per realizzarlo (foto, video, grafica). '
            . 'Niente date o prezzi inventati. Rispondi solo con JSON: {"ideas": [{"title": "...", "caption": "...", "media": "..."}]} nello stesso ordine delle date.';
        $user = 'Cliente: ' . ($customer?->displayName() ?? '') . ($customer?->sector ? " (settore: {$customer->sector})" : '')
            . "\nCanale: {$channel}"
            . ($strategy->objectives ? "\nObiettivi: {$strategy->objectives}" : '')
            . ($strategy->audience ? "\nPubblico: {$strategy->audience}" : '')
            . ($strategy->tone ? "\nTono: {$strategy->tone}" : '')
            . ($strategy->pillarList() !== [] ? "\nRubriche: " . implode('; ', $strategy->pillarList()) : '')
            . ($strategy->dosDonts ? "\nDa fare e da evitare: {$strategy->dosDonts}" : '')
            . ($recent !== [] ? "\nTemi gia' usati di recente: " . implode('; ', $recent) : '')
            . "\nDate e formati:\n" . implode("\n", array_map(static fn (array $s) => "- {$s['date']}: {$s['format']}", $slots));

        $result = $this->container->get(AiService::class)->json($system, $user, 3000);
        $ideas = array_values(array_filter($result['ideas'] ?? [], 'is_array'));
        if ($ideas === []) {
            throw new \RuntimeException("L'IA non ha proposto temi.");
        }

        return $ideas;
    }

    /**
     * Testo proposto dall'IA, con il tono del cliente. Lancia se l'IA non
     * e' configurata o non risponde: lo gestisce chi chiama.
     */
    public function draftCaption(EditorialPost $post): string
    {
        $customer = $this->customers->find((int) $post->customerId);
        $recent = array_slice(array_filter(
            $this->posts->findAll(['customer_id' => $post->customerId], 'publish_date DESC'),
            static fn (EditorialPost $p) => $p->caption && $p->id !== $post->id
        ), 0, 3);

        $system = "Sei il social media manager di un'agenzia di comunicazione italiana. Scrivi didascalie brevi, naturali, "
            . "con un invito all'azione e 3-6 hashtag pertinenti in fondo. Niente emoji in eccesso (al massimo 2). "
            . "Rispondi solo con JSON: {\"caption\": \"...\"}.";
        $user = "Cliente: " . ($customer?->displayName() ?? '') . ($customer?->sector ? " (settore: {$customer->sector})" : '')
            . "\nCanale: {$post->channel}, formato: {$post->format}"
            . "\nTema: {$post->title}"
            . ($post->mediaNotes ? "\nMateriale: {$post->mediaNotes}" : '')
            . $this->strategyNote((int) $post->customerId)
            . ($recent !== [] ? "\nEsempi di testi gia' usati per questo cliente (per il tono):\n- " . implode("\n- ", array_map(static fn (EditorialPost $p) => mb_substr((string) $p->caption, 0, 300), $recent)) : '');

        $result = $this->container->get(AiService::class)->json($system, $user, 800);
        $caption = trim((string) ($result['caption'] ?? ''));
        if ($caption === '') {
            throw new \RuntimeException("L'IA non ha proposto un testo.");
        }

        return $caption;
    }

    /** La strategia del cliente in due righe, per i testi proposti dall'IA. */
    private function strategyNote(int $customerId): string
    {
        $strategy = $this->container->get(EditorialStrategyRepository::class)->findByCustomer($customerId);
        if ($strategy === null) {
            return '';
        }

        return ($strategy->tone ? "\nTono di voce: {$strategy->tone}" : '')
            . ($strategy->audience ? "\nPubblico: {$strategy->audience}" : '')
            . ($strategy->dosDonts ? "\nDa fare e da evitare: {$strategy->dosDonts}" : '');
    }

    /** n giorni lavorativi prima di una data. */
    public static function workdaysBefore(string $date, int $n): string
    {
        $day = $date;
        for ($i = 0; $i < $n; $i++) {
            do {
                $day = date('Y-m-d', strtotime("{$day} -1 day"));
            } while ((int) date('N', strtotime($day)) >= 6);
        }

        return $day < date('Y-m-d') ? Workdays::adjust(date('Y-m-d')) : $day;
    }

    /** "2 post e 1 reel" -> [post, post, reel]; vuoto -> [post]. */
    private static function formatsFromText(string $text): array
    {
        $formats = [];
        if (preg_match_all('/(\d+)\s*(post|caroselli|carosello|reel|storie|storia|video)/i', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as [, $count, $name]) {
                $name = strtolower($name);
                $name = ['caroselli' => 'carosello', 'storie' => 'storia'][$name] ?? $name;
                array_push($formats, ...array_fill(0, max(1, (int) $count), $name));
            }
        }

        return $formats !== [] ? $formats : ['post'];
    }
}
