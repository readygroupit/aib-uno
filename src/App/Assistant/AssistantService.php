<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Core\Container;
use App\Model\AssistantAction;
use App\Model\AssistantRule;
use App\Model\Customer;
use App\Model\Task;
use App\Model\WorkOrder;
use App\Model\WorkOrderMilestone;
use App\Model\WorkOrderTypeStep;
use App\Notifications\NotificationService;
use App\Notifications\NotificationTypes;
use App\Package\PackageCatalogService;
use App\Repository\AssistantActionRepository;
use App\Repository\AssistantRuleRepository;
use App\Repository\CustomerRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderMemberRepository;
use App\Repository\WorkOrderMilestoneRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeRoleRepository;
use App\Repository\WorkOrderTypeStepRepository;
use App\Service\AuthService;
use App\Service\MailService;
use App\Support\ItalianFormat as F;
use App\Work\ProcessService;
use App\Work\WorkOrderService;

/**
 * L'assistente: guarda i progetti (fasi, squadra, date chiave, preventivi,
 * attese del cliente) e dice a ciascuno cosa fare e decidere.
 *
 * - collect(): le cose di oggi, calcolate sul momento dai dati (niente da
 *   tenere allineato). agendaFor() le raggruppa per la giornata di una
 *   persona (home, prompt "assistente").
 * - run(): il giro programmato (job run_assistant). Per le regole che lo
 *   prevedono manda un avviso una volta sola per episodio (es. quel
 *   preventivo inviato quel giorno), e per i solleciti al cliente prepara
 *   il messaggio da confermare o, se la regola e' "in automatico", lo
 *   manda e lo dice. assistant_actions tiene traccia di tutto.
 */
final class AssistantService
{
    private const LATE_SHOWN = 5;

    private Container $container;
    private string $today;
    private ?array $rules = null;
    private array $stepsByType = [];
    private ?array $userNames = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->today = date('Y-m-d');
    }

    /** Per i test e per "come sarebbe domani": la data su cui ragiona. */
    public function setToday(string $date): void
    {
        $this->today = $date;
    }

    /** @return array{enabled:bool, days:?int, autonomy:?string} */
    public function rule(string $code): array
    {
        if ($this->rules === null) {
            $this->rules = $this->container->get(AssistantRuleRepository::class)->byCode();
        }
        $default = AssistantRules::ALL[$code];
        $saved = $this->rules[$code] ?? null;

        return [
            'enabled' => $saved instanceof AssistantRule ? (int) $saved->isEnabled === 1 : true,
            'days' => $saved instanceof AssistantRule && $saved->days !== null ? (int) $saved->days : $default['days'],
            'autonomy' => $default['autonomy'] === null ? null : ($saved instanceof AssistantRule && $saved->autonomy !== null ? $saved->autonomy : $default['autonomy']),
        ];
    }

    /** Le regole con i valori di adesso, per la pagina delle regole. Le righe nascono qui la prima volta. */
    public function rulesForSettings(): array
    {
        $repo = $this->container->get(AssistantRuleRepository::class);
        $saved = $repo->byCode();
        foreach (AssistantRules::ALL as $code => $default) {
            if (!isset($saved[$code])) {
                $repo->insert(['code' => $code, 'is_enabled' => 1, 'days' => $default['days'], 'autonomy' => $default['autonomy']]);
            }
        }
        $this->rules = null;
        $result = [];
        foreach ($repo->byCode() as $code => $row) {
            if (!isset(AssistantRules::ALL[$code])) {
                continue;
            }
            $result[] = ['id' => (int) $row->id, 'code' => $code] + AssistantRules::ALL[$code] + ['current' => $this->rule($code)];
        }

        return $result;
    }

    /**
     * La giornata di una persona: le cose di oggi divise per sezione, i
     * messaggi preparati da confermare, cosa ha fatto l'assistente da solo.
     */
    public function agendaFor(int $userId): array
    {
        $items = $this->preparedItems($userId);
        $actions = $this->container->get(AssistantActionRepository::class);

        $sections = [];
        foreach (AssistantRules::SECTIONS as $key => $meta) {
            $sectionItems = array_values(array_filter($items, static fn (array $i) => $i['section'] === $key));
            if ($sectionItems !== []) {
                $sections[] = ['key' => $key, 'title' => $meta['title'], 'tone' => $meta['tone'], 'items' => $sectionItems];
            }
        }

        $done = array_map(fn (AssistantAction $a) => [
            'title' => (string) $a->title,
            'when' => F::relativeDays(substr((string) $a->decidedAt, 0, 10), $this->today),
            'stage' => (string) $a->stage,
        ], $actions->recentFor($userId, 7));

        return ['sections' => $sections, 'count' => count($items), 'done' => $done];
    }

    /**
     * Le cose di oggi di una persona, con i pulsanti giusti per i solleciti
     * (preparato, gia' deciso, da preparare) e le tante attivita' vecchie in
     * ritardo raccolte in una riga.
     *
     * @return list<array<string,mixed>>
     */
    private function preparedItems(int $userId): array
    {
        $items = array_values(array_filter($this->collect($userId), static fn (array $i) => $i['user'] === $userId));
        $actions = $this->container->get(AssistantActionRepository::class);
        // Un sollecito gia' preparato o deciso: si mostra quello, non di nuovo l'avviso.
        $items = array_values(array_filter(array_map(function (array $item) use ($actions) {
            if (!isset($item['proposal'])) {
                return $item;
            }
            $action = $actions->findByKey('proposal:' . $item['dedupe']);
            if ($action instanceof AssistantAction && $action->stage !== 'proposta') {
                return null;
            }
            if ($action instanceof AssistantAction) {
                $item['actions'] = array_merge([
                    ['label' => 'Rileggi e manda', 'href' => '/assistente/azioni/' . $action->id, 'variant' => 'primary', 'icon' => 'envelope'],
                    ['label' => 'Non serve', 'action' => '/assistente/azioni/' . $action->id . '/scarta', 'variant' => 'ghost'],
                ], $item['actions']);
                $item['why'] .= ' Il messaggio e\' pronto.';
            } elseif ($item['autonomy'] !== AssistantRules::AUTONOMY_NOTICE) {
                $item['actions'] = array_merge([
                    ['label' => 'Prepara il messaggio', 'action' => '/assistente/prepara', 'body' => ['rule' => $item['rule'], 'key' => $item['dedupe']], 'variant' => 'primary', 'icon' => 'wand-magic-sparkles'],
                ], $item['actions']);
            }

            return $item;
        }, $items)));

        // Tante attivita' vecchie in ritardo (es. arrivate da un import) non
        // devono coprire il resto: le prime 5, poi un rimando all'elenco.
        $late = array_values(array_filter($items, static fn (array $i) => $i['rule'] === 'task_late'));
        if (count($late) > self::LATE_SHOWN) {
            $keep = array_slice($late, 0, self::LATE_SHOWN);
            $items = array_values(array_filter($items, static fn (array $i) => $i['rule'] !== 'task_late' || in_array($i, $keep, true)));
            $items[] = $this->item('task_late', $userId, 'Altre ' . (count($late) - self::LATE_SHOWN) . " attivita' in ritardo", "Rivedile: falle, spostale o chiudile se non servono piu'.", '/attivita', null, null, 'task_late:more', ['orderId' => null, 'context' => null, 'contextHref' => null], [
                ['label' => 'Le mie attivita\'', 'href' => '/attivita', 'variant' => 'secondary'],
            ]);
        }

        return array_map(fn (array $i) => $this->withWho($i, $userId), $items);
    }

    /** @var array<int,array{name:string,initials:string,avatar:?string}>|null */
    private ?array $people = null;

    /**
     * Su ogni cosa che riguarda un'attivita': a chi tocca (anche se non e'
     * chi guarda, es. il responsabile vede che la fase e' di Giuseppe).
     */
    private function withWho(array $item, int $viewer): array
    {
        $taskId = ($item['entityType'] ?? null) === 'tasks' && !empty($item['entityId']) ? (int) $item['entityId'] : (int) ($item['whoTaskId'] ?? 0);
        if ($taskId <= 0) {
            return $item;
        }
        $task = $this->container->get(TaskRepository::class)->find($taskId);
        if (!$task instanceof Task || $task->assignedUserId === null) {
            $item['who'] = ['name' => 'nessuno', 'initials' => '?', 'avatar' => null, 'me' => false, 'missing' => true];

            return $item;
        }
        if ($this->people === null) {
            $this->people = [];
            foreach ($this->container->get(\App\Repository\UserRepository::class)->findAll() as $user) {
                $name = trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username;
                $this->people[(int) $user->id] = ['name' => $name, 'initials' => F::initials($name), 'avatar' => \App\Service\UserAvatarService::url($user->avatarPath ?? null)];
            }
        }
        $person = $this->people[(int) $task->assignedUserId] ?? ['name' => '—', 'initials' => '?', 'avatar' => null];
        $item['who'] = $person + ['me' => (int) $task->assignedUserId === $viewer, 'missing' => false];

        return $item;
    }

    /**
     * La home: i colleghi digitali, ognuno con le sue cose piu' urgenti o
     * importanti (al massimo 3), anche quelle non immediate.
     *
     * @return array{summary:string, colleagues:list<array<string,mixed>>, count:int}
     */
    public function colleaguesFor(int $userId): array
    {
        $items = $this->preparedItems($userId);
        $config = (array) ($this->container->get(\App\Core\Config::class)->get('assistant.colleagues') ?? []);
        $rank = ['oggi' => 0, 'presto' => 1, 'importante' => 2, 'idea' => 3];
        $tags = ['oggi' => 'Oggi', 'presto' => 'Presto', 'importante' => 'Importante', 'idea' => 'Idea'];
        $colleagues = [];
        $counts = ['oggi' => 0, 'presto' => 0, 'importante' => 0, 'idea' => 0];
        foreach (AssistantRules::COLLEAGUES as $key => $meta) {
            $meta = array_merge($meta, array_intersect_key((array) ($config[$key] ?? []), array_flip(['name', 'role', 'color', 'more'])));
            $mine = array_values(array_filter($items, static fn (array $i) => in_array($i['rule'], $meta['rules'], true)));
            foreach ($mine as &$item) {
                $item['urgency'] = AssistantRules::URGENCY[$item['rule']] ?? 'importante';
                $item['tag'] = $item['when'] ?? $tags[$item['urgency']];
                $counts[$item['urgency']]++;
            }
            unset($item);
            usort($mine, static fn (array $a, array $b) => $rank[$a['urgency']] <=> $rank[$b['urgency']]);
            $byUrgency = array_count_values(array_column($mine, 'urgency'));
            $colleagues[] = [
                'key' => $key,
                'name' => (string) $meta['name'],
                'initial' => mb_strtoupper(mb_substr((string) $meta['name'], 0, 1)),
                'role' => (string) $meta['role'],
                'color' => (string) $meta['color'],
                'count' => count($mine),
                'tone' => isset($byUrgency['oggi']) ? 'urgent' : (isset($byUrgency['presto']) ? 'soon' : (count($mine) > 0 ? 'calm' : 'none')),
                'say' => self::colleagueLine($byUrgency),
                'items' => array_map(static fn (array $i) => array_intersect_key($i, array_flip(['title', 'why', 'href', 'context', 'contextHref', 'actions', 'urgency', 'tag', 'who'])), array_slice($mine, 0, 3)),
                'more' => count($mine) > 3 ? ((string) $meta['more']) . ' (' . (count($mine) - 3) . ' in piu\')' : null,
            ];
        }
        $parts = array_filter([
            $counts['oggi'] > 0 ? F::plural($counts['oggi'], 'cosa per oggi', 'cose per oggi') : null,
            $counts['presto'] > 0 ? $counts['presto'] . ' a breve' : null,
            $counts['importante'] > 0 ? $counts['importante'] . ' important' . ($counts['importante'] === 1 ? 'e' : 'i') : null,
            $counts['idea'] > 0 ? F::plural($counts['idea'], 'idea', 'idee') : null,
        ]);

        return [
            'summary' => $parts !== [] ? 'Oggi: ' . implode(', ', $parts) . '.' : "Tutto in ordine: niente di urgente.",
            'colleagues' => $colleagues,
            'count' => array_sum($counts),
        ];
    }

    /**
     * La pagina degli agenti: per ognuno le sue cose (tutte, per urgenza),
     * cosa ha fatto per la persona, come lavora. Tutto di chi e' collegato.
     *
     * @return list<array<string,mixed>>
     */
    public function agentsFor(int $userId, string $firstName, bool $canManage): array
    {
        $items = $this->preparedItems($userId);
        $config = (array) ($this->container->get(\App\Core\Config::class)->get('assistant.colleagues') ?? []);
        $groups = ['oggi' => 'Oggi', 'presto' => 'A breve', 'importante' => 'Da non dimenticare', 'idea' => 'Idee'];
        $tags = ['oggi' => 'Oggi', 'presto' => 'Presto', 'importante' => 'Importante', 'idea' => 'Idea'];
        $activity = $this->container->get(AssistantActionRepository::class)->activityFor($userId, 14);
        $notices = in_array('notifications', PackageCatalogService::installedNames(), true) ? $this->container->get(\App\Repository\NotificationRepository::class) : null;
        $result = [];

        foreach (AssistantRules::COLLEAGUES as $key => $meta) {
            $meta = array_merge($meta, array_intersect_key((array) ($config[$key] ?? []), array_flip(['name', 'role', 'color', 'more', 'ask'])));
            $mine = array_values(array_filter($items, static fn (array $i) => in_array($i['rule'], $meta['rules'], true)));
            $byGroup = [];
            foreach ($mine as $item) {
                $urgency = AssistantRules::URGENCY[$item['rule']] ?? 'importante';
                $item['urgency'] = $urgency;
                $item['tag'] = $item['when'] ?? $tags[$urgency];
                $byGroup[$urgency][] = array_intersect_key($item, array_flip(['title', 'why', 'href', 'context', 'contextHref', 'actions', 'urgency', 'tag', 'who']));
            }
            $sections = [];
            foreach ($groups as $urgency => $title) {
                if (!empty($byGroup[$urgency])) {
                    $sections[] = ['key' => $urgency, 'title' => $title, 'items' => $byGroup[$urgency]];
                }
            }
            $counts = array_map('count', $byGroup);

            $done = [];
            foreach ($activity as $action) {
                if (!in_array($action->ruleCode, $meta['rules'], true)) {
                    continue;
                }
                $done[] = [
                    'text' => match ($action->stage) {
                        'eseguita' => 'Ho mandato: ' . $action->title,
                        'proposta' => 'Ti ho preparato il messaggio: ' . $action->title,
                        'scartata' => 'Messaggio non mandato (hai deciso tu): ' . $action->title,
                        default => 'Ti ho avvisato: ' . $action->title,
                    },
                    'when' => $this->moment((string) ($action->decidedAt ?? $action->createdAt)),
                    'at' => (string) ($action->decidedAt ?? $action->createdAt),
                ];
            }
            if ($notices !== null && ($meta['notices'] ?? []) !== []) {
                foreach ($notices->recentOfTypes($userId, $meta['notices'], 14, 10) as $n) {
                    $done[] = ['text' => 'Ti ho avvisato: ' . $n->title, 'when' => $this->moment((string) $n->createdAt), 'at' => (string) $n->createdAt];
                }
            }
            usort($done, static fn (array $a, array $b) => strcmp($b['at'], $a['at']));

            $rules = [];
            foreach ($meta['rules'] as $code) {
                $def = AssistantRules::ALL[$code];
                $rule = $this->rule($code);
                $rules[] = [
                    'code' => $code,
                    'label' => $def['label'],
                    'when' => $def['days'] !== null ? $rule['days'] . ' ' . ($def['daysLabel'] ?? 'giorni') : $def['help'],
                    'autonomy' => !$rule['enabled'] ? 'Spenta' : match ($rule['autonomy']) {
                        AssistantRules::AUTONOMY_AUTO => 'Fa da solo',
                        AssistantRules::AUTONOMY_CONFIRM => 'Chiede conferma',
                        AssistantRules::AUTONOMY_NOTICE => 'Ti avvisa',
                        default => $def['notify'] ? 'Ti avvisa' : 'Te lo mostra',
                    },
                    'tone' => !$rule['enabled'] ? 'off' : ($rule['autonomy'] === AssistantRules::AUTONOMY_AUTO ? 'auto' : ($rule['autonomy'] === AssistantRules::AUTONOMY_CONFIRM ? 'confirm' : 'notice')),
                ];
            }

            $first = $mine !== [] ? $sections[0]['items'][0] : null;
            $hello = 'Ciao' . ($firstName !== '' ? " {$firstName}" : '') . '. ';
            $hello .= $mine === []
                ? 'Per ora non ho niente per te: ti scrivo appena serve.'
                : 'Ho per te ' . lcfirst(rtrim(self::colleagueLine($counts), '.')) . '. La prima: ' . "\u{ab}{$first['title']}\u{bb}" . ($first['context'] && !str_contains((string) $first['title'], (string) $first['context']) ? " ({$first['context']})" : '') . '.';

            $result[] = [
                'key' => $key,
                'name' => (string) $meta['name'],
                'role' => (string) $meta['role'],
                'color' => (string) $meta['color'],
                'count' => count($mine),
                'tone' => isset($counts['oggi']) ? 'urgent' : (isset($counts['presto']) ? 'soon' : (count($mine) > 0 ? 'calm' : 'none')),
                'hello' => $hello,
                'sections' => $sections,
                'done' => array_slice(array_map(static fn (array $d) => array_diff_key($d, ['at' => 1]), $done), 0, 8),
                'rules' => $rules,
                'ask' => 'Chiedi a ' . $meta['name'] . ': "' . ($meta['ask'] ?? '') . '"',
                'rulesHref' => $canManage ? '/assistente/regole#' . $key : null,
            ];
        }

        return $result;
    }

    private function moment(string $at): string
    {
        $day = substr($at, 0, 10);

        return ($day === $this->today ? 'oggi' : F::relativeDays($day, $this->today)) . ', ' . substr($at, 11, 5);
    }

    /** @param array<string,int> $byUrgency */
    private static function colleagueLine(array $byUrgency): string
    {
        if ($byUrgency === []) {
            return 'Tutto a posto, per ora.';
        }
        $parts = array_filter([
            isset($byUrgency['oggi']) ? ($byUrgency['oggi'] === 1 ? '1 cosa per oggi' : "{$byUrgency['oggi']} cose per oggi") : null,
            isset($byUrgency['presto']) ? ($byUrgency['presto'] === 1 ? '1 cosa a breve' : "{$byUrgency['presto']} cose a breve") : null,
            isset($byUrgency['importante']) ? ($byUrgency['importante'] === 1 ? '1 cosa da non dimenticare' : "{$byUrgency['importante']} cose da non dimenticare") : null,
            isset($byUrgency['idea']) ? ($byUrgency['idea'] === 1 ? "un'idea" : "{$byUrgency['idea']} idee") : null,
        ]);

        return ucfirst(implode(', ', $parts)) . '.';
    }

    /**
     * Il giro programmato: avvisi (una volta per episodio) e solleciti
     * preparati o mandati secondo l'autonomia della regola.
     *
     * @return array{notified:int, proposed:int, sent:int}
     */
    public function run(): array
    {
        $result = ['notified' => 0, 'proposed' => 0, 'sent' => 0];
        $actions = $this->container->get(AssistantActionRepository::class);
        $notifications = $this->container->get(NotificationService::class);

        foreach ($this->collect(null) as $item) {
            if (isset($item['proposal']) && $item['autonomy'] !== AssistantRules::AUTONOMY_NOTICE) {
                if ($actions->findByKey('proposal:' . $item['dedupe']) !== null) {
                    continue;
                }
                $action = $this->prepare($item);
                if ($item['autonomy'] === AssistantRules::AUTONOMY_AUTO) {
                    $this->send((int) $action->id, null);
                    $notifications->notify($item['user'], NotificationTypes::ASSISTANT, "Ho mandato un sollecito: {$item['context']}", $action->emailSubject, $item['contextHref'], 'assistant_actions', (int) $action->id);
                    $result['sent']++;
                } else {
                    $notifications->notify($item['user'], NotificationTypes::ASSISTANT, "Messaggio pronto da rileggere: {$item['title']}", $item['why'], '/assistente/azioni/' . $action->id, 'assistant_actions', (int) $action->id);
                    $result['proposed']++;
                }
                continue;
            }
            if (!$item['notify']) {
                continue;
            }
            $key = 'notice:' . $item['dedupe'];
            if ($actions->findByKey($key) !== null) {
                continue;
            }
            $actions->insert([
                'rule_code' => $item['rule'],
                'user_id' => $item['user'],
                'entity_type' => $item['entityType'],
                'entity_id' => $item['entityId'],
                'dedupe_key' => $key,
                'title' => mb_substr($item['title'], 0, 200),
                'body' => $item['why'],
                'stage' => 'segnalata',
            ]);
            $notifications->notify($item['user'], NotificationTypes::ASSISTANT, $item['title'], $item['why'], $item['href'], $item['entityType'], $item['entityId']);
            $result['notified']++;
        }

        return $result;
    }

    /** "Prepara il messaggio" dalla giornata: il sollecito di quella cosa, da rileggere. */
    public function prepareByKey(int $userId, string $rule, string $key): ?AssistantAction
    {
        $existing = $this->container->get(AssistantActionRepository::class)->findByKey('proposal:' . $key);
        if ($existing instanceof AssistantAction) {
            return $existing;
        }
        foreach ($this->collect($userId) as $item) {
            if ($item['rule'] === $rule && $item['dedupe'] === $key && $item['user'] === $userId && isset($item['proposal'])) {
                return $this->prepare($item);
            }
        }

        return null;
    }

    /**
     * Manda il messaggio di una proposta (con il testo eventualmente
     * cambiato da chi l'ha riletto) e ne lascia traccia.
     */
    public function send(int $actionId, ?int $decidedBy): string
    {
        $repo = $this->container->get(AssistantActionRepository::class);
        $action = $repo->find($actionId);
        if (!$action instanceof AssistantAction || $action->stage !== 'proposta') {
            return 'Gia\' deciso.';
        }
        $to = array_values(array_filter(array_map('trim', explode(',', (string) $action->emailTo))));
        if ($to === [] || trim((string) $action->emailSubject) === '') {
            return 'Manca l\'indirizzo o l\'oggetto.';
        }
        $mail = $this->container->get(MailService::class);
        $sent = 0;
        foreach ($to as $address) {
            $sent += $mail->send($address, (string) $action->emailSubject, (string) $action->emailBody) ? 1 : 0;
        }
        $repo->update($actionId, ['stage' => 'eseguita', 'decided_at' => date('Y-m-d H:i:s'), 'decided_by' => $decidedBy]);
        if ($action->entityType === 'customers' && $action->entityId !== null && $sent > 0) {
            if (in_array('client_health', PackageCatalogService::installedNames(), true)) {
                $this->container->get(\App\Repository\CustomerTouchpointRepository::class)->insert([
                    'customer_id' => $action->entityId,
                    'contact_date' => date('Y-m-d'),
                    'channel' => 'email',
                    'user_id' => $decidedBy ?? $action->userId,
                    'mood' => 'neutro',
                    'summary' => 'Email: ' . $action->emailSubject,
                ]);
            }
            $this->container->get(\App\Customers\CustomerFollowUpService::class)->contacted((int) $action->entityId);
        }
        if ($action->entityType === 'work_orders' && $action->entityId !== null) {
            $data = ['last_activity_at' => date('Y-m-d H:i:s')];
            if ($action->ruleCode === 'client_no_answer') {
                $data['last_reminder_at'] = date('Y-m-d H:i:s');
            }
            $this->container->get(WorkOrderRepository::class)->update((int) $action->entityId, $data);
        }

        return $sent > 0 ? 'Mandato a ' . implode(', ', $to) . '.' : 'Non partito: controlla l\'invio delle email.';
    }

    public function dismiss(int $actionId, int $userId): bool
    {
        $repo = $this->container->get(AssistantActionRepository::class);
        $action = $repo->find($actionId);
        if (!$action instanceof AssistantAction || $action->stage !== 'proposta') {
            return false;
        }
        $repo->update($actionId, ['stage' => 'scartata', 'decided_at' => date('Y-m-d H:i:s'), 'decided_by' => $userId]);

        return true;
    }

    private function prepare(array $item): AssistantAction
    {
        $order = !empty($item['orderId']) ? $this->container->get(WorkOrderRepository::class)->find((int) $item['orderId']) : null;
        $customerId = $item['customerId'] ?? $order?->customerId;
        $customer = $customerId !== null ? $this->container->get(CustomerRepository::class)->find((int) $customerId) : null;
        $signature = trim(($this->userNames()[$item['user']] ?? '') . "\n" . (defined('APP_NAME') ? APP_NAME : ''));
        $text = $this->container->get(AssistantWriter::class)->reminder(
            $item['proposal']['kind'],
            $order,
            $customer instanceof Customer ? $customer : null,
            (string) ($item['proposal']['what'] ?? ''),
            $signature
        );
        $process = $this->container->get(ProcessService::class);
        $repo = $this->container->get(AssistantActionRepository::class);
        $id = $repo->insert([
            'rule_code' => $item['rule'],
            'user_id' => $item['user'],
            'entity_type' => $order !== null ? 'work_orders' : 'customers',
            'entity_id' => $order !== null ? $order->id : $customerId,
            'dedupe_key' => 'proposal:' . $item['dedupe'],
            'title' => mb_substr($item['title'], 0, 200),
            'body' => $item['why'] . ($text['ai'] ? ' Testo scritto dall\'assistente sul cliente.' : ''),
            'email_to' => $order !== null ? $process->customerEmails($order) : ($customerId !== null ? $process->emailsForCustomer((int) $customerId) : null),
            'email_subject' => $text['subject'],
            'email_body' => $text['body'],
            'stage' => 'proposta',
        ]);

        return $repo->find($id);
    }

    /**
     * Tutte le cose di oggi, per tutti (null) o per una persona.
     *
     * @return list<array<string,mixed>>
     */
    public function collect(?int $forUser): array
    {
        $customerItems = [];
        $this->customerItems($customerItems);
        if (!in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            $items = array_merge($customerItems, $forUser !== null ? $this->lateTasks($forUser, []) : []);

            return $forUser === null ? $items : array_values(array_filter($items, static fn (array $i) => $i['user'] === $forUser));
        }
        $portfolio = $this->rule('portfolio');
        $orders = $this->container->get(WorkOrderRepository::class)->findForBoard(date('Y-m-d', strtotime($this->today . ' -' . max(1, (int) $portfolio['days']) . ' days')));
        $ids = array_map(static fn (WorkOrder $o) => (int) $o->id, $orders);
        $tasksByOrder = $this->container->get(TaskRepository::class)->findForEntities(WorkOrderService::ENTITY, $ids);
        $membersByOrder = $this->container->get(WorkOrderMemberRepository::class)->findForOrders($ids);
        $milestonesByOrder = $this->container->get(WorkOrderMilestoneRepository::class)->findForOrders($ids);
        $customers = $this->customerNames($orders);

        $items = [];
        $shownTasks = [];
        foreach ($orders as $order) {
            $id = (int) $order->id;
            $tasks = $tasksByOrder[$id] ?? [];
            $members = array_map(static fn ($m) => (int) $m->userId, $membersByOrder[$id] ?? []);
            $ctx = [
                'orderId' => $id,
                'context' => trim($order->title . ($order->customerId !== null && isset($customers[(int) $order->customerId]) ? ' · ' . $customers[(int) $order->customerId] : '')),
                'contextHref' => '/commesse/' . $id,
            ];
            $steps = $this->steps($order->workOrderTypeId !== null ? (int) $order->workOrderTypeId : null);

            if ($order->stage === 'consegnata') {
                $this->portfolioItem($items, $order, $tasks, $ctx);
                continue;
            }
            if (!$order->isActive() || $order->stage === 'in pausa') {
                continue;
            }

            $next = null;
            foreach ($tasks as $task) {
                if ($task->isOpen()) {
                    $next = $task;
                    break;
                }
            }
            $nextStep = $next !== null && $next->sourceStepId !== null ? ($steps[(int) $next->sourceStepId] ?? null) : null;

            if ($next !== null && $next->assignedUserId !== null) {
                $who = (int) $next->assignedUserId;
                $kind = $nextStep?->kind;
                if ($kind === ProcessService::KIND_APPROVAL) {
                    $this->approvalItem($items, $next, $tasks, $ctx);
                    $shownTasks[(int) $next->id] = true;
                } elseif ($kind === ProcessService::KIND_MILESTONE) {
                    $this->milestoneToSetItem($items, $next, $nextStep, $milestonesByOrder[$id] ?? [], $ctx);
                    $shownTasks[(int) $next->id] = true;
                } elseif ((int) $next->waitsForClient !== 1 && $this->rule('step_ready')['enabled']) {
                    $items[] = $this->item('step_ready', $who, "Tocca a te: {$next->title}", $this->stepWhy($next, $tasks), '/attivita/' . $next->id, 'tasks', (int) $next->id, 'step_ready:' . $next->id, $ctx, [
                        ProcessService::guidedAction($kind, (int) $next->id)
                            ?? ['label' => 'Fatta', 'action' => '/attivita/' . $next->id . '/fatta', 'variant' => 'secondary', 'icon' => 'check'],
                    ]);
                    $shownTasks[(int) $next->id] = true;
                }
            }

            $this->roleMissingItems($items, $order, $membersByOrder[$id] ?? [], $next, $ctx);
            $this->milestoneSoonItems($items, $order, $tasks, $steps, $milestonesByOrder[$id] ?? [], $members, $ctx);
            $this->followUpItems($items, $order, $next, $ctx);
        }

        $items = array_merge($items, $customerItems);
        if ($forUser !== null) {
            $items = array_merge($items, $this->lateTasks($forUser, $shownTasks));
        }

        return $forUser === null ? $items : array_values(array_filter($items, static fn (array $i) => $i['user'] === $forUser));
    }

    /**
     * I clienti da seguire: da risentire oggi, non sentiti da un po', i
     * periodi forti che li riguardano, un servizio che manca.
     */
    private function customerItems(array &$items): void
    {
        $installed = PackageCatalogService::installedNames();
        if (!in_array('customers', $installed, true)) {
            return;
        }
        $due = $this->rule('contact_due');
        $silent = $this->rule('customer_silent');
        $seasonal = $this->rule('seasonal');
        $idea = $this->rule('service_idea');
        if (!$due['enabled'] && !$silent['enabled'] && !$seasonal['enabled'] && !$idea['enabled']) {
            return;
        }
        $followUp = $this->container->get(\App\Customers\CustomerFollowUpService::class);
        $last = in_array('client_health', $installed, true) ? $this->container->get(\App\Repository\CustomerTouchpointRepository::class)->lastPerCustomer() : [];
        $profiles = [];
        if (\App\Knowledge\KnowledgeService::isInstalled()) {
            foreach ($this->container->get(\App\Repository\CustomerProfileRepository::class)->findAll() as $profile) {
                $profiles[(int) $profile->customerId] = $profile;
            }
        }
        $upcoming = $seasonal['enabled'] ? $this->container->get(SeasonalCalendar::class)->upcoming($this->today) : [];
        $ideas = $idea['enabled'] && in_array('customer_services', $installed, true) ? $this->serviceIdeas() : [];

        foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
            if (!$followUp->isFollowed($customer)) {
                continue;
            }
            $owner = $followUp->ownerOf($customer);
            if ($owner === null) {
                continue;
            }
            $id = (int) $customer->id;
            $name = $customer->displayName();
            $ctx = ['orderId' => null, 'customerId' => $id, 'context' => $name, 'contextHref' => '/clienti/' . $id];
            $register = ['label' => 'Registra il contatto', 'href' => '/clienti/contatti/nuovo?customer_id=' . $id, 'variant' => 'secondary', 'icon' => 'phone'];
            $isClient = $customer->commercialStage === 'cliente' || ($customer->commercialStage === null && $customer->relationship === 'cliente');

            if ($due['enabled'] && $customer->nextContactDate !== null && $customer->nextContactDate <= $this->today) {
                $note = trim((string) $customer->nextContactNote);
                $items[] = $this->item('contact_due', $owner, "Risenti {$name}",
                    ($note !== '' ? "Per: {$note}. " : '') . 'Previsto ' . F::relativeDays((string) $customer->nextContactDate, $this->today) . ($customer->temperature !== null ? " (cliente {$customer->temperature})." : '.'),
                    '/clienti/' . $id, 'customers', $id, "contact_due:{$id}:{$customer->nextContactDate}", $ctx, [
                        $register,
                        ['label' => 'Rimanda', 'action' => '/assistente/rimanda', 'body' => ['customer' => (string) $id], 'variant' => 'ghost'],
                    ], true, ['kind' => 'followup', 'what' => $note], $due['autonomy']);
            } elseif ($silent['enabled'] && $isClient && ($customer->nextContactDate === null || $customer->nextContactDate <= $this->today)) {
                $lastDate = isset($last[$id]) ? (string) $last[$id]->contactDate : substr((string) $customer->createdAt, 0, 10);
                $limit = (int) $silent['days'];
                if ($customer->importance === 'chiave') {
                    $limit = max(1, intdiv($limit, 2));
                }
                $since = F::daysFrom($this->today, $lastDate);
                if ($since >= $limit) {
                    $items[] = ['when' => $customer->importance === 'chiave' ? 'Cliente chiave' : 'Da ' . F::plural($since, 'giorno', 'giorni')] + $this->item('customer_silent', $owner, "Non senti {$name} da " . F::plural($since, 'giorno', 'giorni'),
                        (isset($last[$id]) ? 'Ultimo contatto: ' . mb_substr(trim((string) $last[$id]->summary), 0, 120) . '.' : 'Nessun contatto registrato.') . ($customer->importance === 'chiave' ? ' E\' un cliente chiave.' : ''),
                        '/clienti/' . $id, 'customers', $id, "customer_silent:{$id}:{$lastDate}", $ctx, [$register], true, ['kind' => 'followup', 'what' => ''], $silent['autonomy']);
                }
            }

            if ($upcoming !== [] && ($isClient || $customer->commercialStage === 'in trattativa')) {
                $profile = $profiles[$id] ?? null;
                $text = implode(' ', [(string) $customer->sector, (string) $customer->notes, (string) $customer->companyName,
                    (string) ($profile?->seasonality), (string) ($profile?->products), (string) ($profile?->history), (string) ($profile?->notes)]);
                foreach ($upcoming as $u) {
                    $event = $u['event'];
                    if (!SeasonalCalendar::matches($event, $text)) {
                        continue;
                    }
                    $when = $u['daysTo'] === 0 ? 'oggi' : 'tra ' . F::plural($u['daysTo'], 'giorno', 'giorni');
                    $items[] = ['when' => "{$event->name} {$when}"] + $this->item('seasonal', $owner, "Proponi un piano per {$event->name}: {$name}",
                        trim((string) $event->idea) . ' Contattalo ora per preparare il piano insieme (' . F::longDate($u['date']) . ').',
                        '/clienti/' . $id, 'customers', $id, "seasonal:{$event->id}:{$id}:" . substr($u['date'], 0, 4), $ctx, [$register], true,
                        ['kind' => 'seasonal', 'what' => $event->name . ': ' . trim((string) $event->idea)], $seasonal['autonomy']);
                }
            }

            if ($isClient && isset($ideas[$id])) {
                [$serviceName, $because, $count, $serviceId] = $ideas[$id];
                $period = intdiv((int) (strtotime($this->today) / 86400), max(1, (int) $idea['days']));
                $items[] = $this->item('service_idea', $owner, "Proponi {$serviceName} a {$name}",
                    "Ha {$because}: " . ($count === 1 ? "1 cliente con lo stesso servizio ha preso anche {$serviceName}." : "{$count} clienti con gli stessi servizi hanno preso anche {$serviceName}."),
                    '/clienti/' . $id, 'customers', $id, "service_idea:{$id}:{$serviceId}:{$period}", $ctx, [
                        ['label' => 'Prepara un preventivo', 'href' => '/preventivi/nuovo?customer_id=' . $id, 'variant' => 'secondary', 'icon' => 'file-invoice'],
                    ]);
            }
        }
    }

    /**
     * Per ogni cliente, il servizio del listino che non ha e che hanno piu'
     * spesso i clienti con i suoi stessi servizi (almeno 2).
     *
     * @return array<int, array{0:string, 1:string, 2:int, 3:int}> cliente => [servizio, perche', quanti, id servizio]
     */
    private function serviceIdeas(): array
    {
        $owned = [];
        foreach ($this->container->get(\App\Repository\CustomerServiceRepository::class)->findAll() as $sold) {
            if ($sold->serviceId !== null) {
                $owned[(int) $sold->customerId][(int) $sold->serviceId] = true;
            }
        }
        $names = [];
        if (in_array('services', PackageCatalogService::installedNames(), true)) {
            foreach ($this->container->get(\App\Repository\ServiceRepository::class)->findAll() as $service) {
                if ($service->stage !== 'non disponibile') {
                    $names[(int) $service->id] = (string) $service->name;
                }
            }
        }
        $together = [];
        foreach ($owned as $services) {
            foreach (array_keys($services) as $a) {
                foreach (array_keys($services) as $b) {
                    if ($a !== $b) {
                        $together[$a][$b] = ($together[$a][$b] ?? 0) + 1;
                    }
                }
            }
        }
        $result = [];
        foreach ($owned as $customerId => $services) {
            $scores = [];
            foreach (array_keys($services) as $have) {
                foreach ($together[$have] ?? [] as $other => $count) {
                    if (!isset($services[$other]) && isset($names[$other])) {
                        $scores[$other] = ($scores[$other] ?? 0) + $count;
                    }
                }
            }
            arsort($scores);
            $best = array_key_first($scores);
            if ($best !== null && $scores[$best] >= 2) {
                $result[$customerId] = [$names[$best], implode(', ', array_filter(array_map(static fn ($s) => $names[$s] ?? null, array_keys($services)))), $scores[$best], $best];
            }
        }

        return $result;
    }

    private function approvalItem(array &$items, Task $task, array $tasks, array $ctx): void
    {
        $rule = $this->rule('approval_waiting');
        if (!$rule['enabled']) {
            return;
        }
        $since = $this->previousDoneAt($task, $tasks) ?? substr((string) $task->createdAt, 0, 10);
        $days = F::daysFrom($this->today, $since);
        $items[] = ($days > 0 ? ['when' => 'Da ' . F::plural($days, 'giorno', 'giorni')] : []) + $this->item('approval_waiting', (int) $task->assignedUserId, "Da approvare: {$task->title}",
            $days > 0 ? 'Aspetta il tuo ok da ' . F::plural($days, 'giorno', 'giorni') . '.' : 'Appena arrivato.',
            '/attivita/' . $task->id, 'tasks', (int) $task->id, 'approval_waiting:' . $task->id, $ctx, [
                ['label' => 'Approva', 'action' => "/attivita/{$task->id}/approva", 'variant' => 'primary', 'icon' => 'thumbs-up'],
                ['label' => 'Rimanda indietro', 'action' => "/attivita/{$task->id}/rimanda", 'variant' => 'secondary', 'ask' => 'Cosa va sistemato?'],
            ], $days >= (int) $rule['days']);
    }

    /** @param WorkOrderMilestone[] $milestones */
    private function milestoneToSetItem(array &$items, Task $task, WorkOrderTypeStep $step, array $milestones, array $ctx): void
    {
        if (!$this->rule('milestone_to_set')['enabled']) {
            return;
        }
        foreach ($milestones as $milestone) {
            if ((int) $milestone->milestoneId !== (int) $step->milestoneId || $milestone->scheduledAt !== null) {
                continue;
            }
            $why = (int) $milestone->sendClientEmail === 1
                ? "Tocca a te fissarla. Quando la fissi vedi l'email per il cliente, gia' scritta, da rileggere."
                : 'Tocca a te fissarla: la squadra riceve l\'invito in calendario.';
            $items[] = $this->item('milestone_to_set', (int) $task->assignedUserId, "Fissa la data: {$milestone->label}", $why,
                '/commesse/date/' . $milestone->id, 'work_order_milestones', (int) $milestone->id, 'milestone_to_set:' . $milestone->id, $ctx, [
                    ['label' => 'Fissa la data', 'href' => '/commesse/date/' . $milestone->id, 'variant' => 'primary', 'icon' => 'calendar'],
                ]);
        }
    }

    private function roleMissingItems(array &$items, WorkOrder $order, array $members, ?Task $next, array $ctx): void
    {
        if (!$this->rule('role_missing')['enabled'] || $order->workOrderTypeId === null) {
            return;
        }
        $covered = array_map(static fn ($m) => (int) $m->roleId, $members);
        $who = array_values(array_unique(array_filter([$order->ownerUserId !== null ? (int) $order->ownerUserId : null, $next?->assignedUserId !== null ? (int) $next->assignedUserId : null])));
        foreach ($this->container->get(WorkOrderTypeRoleRepository::class)->findByWorkOrderType((int) $order->workOrderTypeId) as $role) {
            if ((int) $role->isRequired !== 1 || in_array((int) $role->id, $covered, true)) {
                continue;
            }
            foreach ($who as $userId) {
                $items[] = $this->item('role_missing', $userId, "Manca il ruolo {$role->label}", 'Nessuno nella squadra ha questo ruolo: le sue fasi sono senza nessuno.',
                    "/commesse/squadra/nuovo?work_order_id={$order->id}&role_id={$role->id}", 'work_orders', (int) $order->id, "role_missing:{$order->id}:{$role->id}", $ctx, [
                        ['label' => 'Scegli chi', 'href' => "/commesse/squadra/nuovo?work_order_id={$order->id}&role_id={$role->id}", 'variant' => 'primary', 'icon' => 'user'],
                    ]);
            }
        }
    }

    /**
     * @param Task[] $tasks
     * @param array<int,WorkOrderTypeStep> $steps
     * @param WorkOrderMilestone[] $milestones
     * @param int[] $members
     */
    private function milestoneSoonItems(array &$items, WorkOrder $order, array $tasks, array $steps, array $milestones, array $members, array $ctx): void
    {
        $soon = $this->rule('milestone_soon');
        $missing = $this->rule('work_missing');
        foreach ($milestones as $milestone) {
            if ($milestone->scheduledAt === null) {
                continue;
            }
            $date = substr((string) $milestone->scheduledAt, 0, 10);
            $daysTo = F::daysFrom($date, $this->today);
            if ($daysTo < 0) {
                continue;
            }
            $when = ($daysTo === 0 ? 'oggi' : ($daysTo === 1 ? 'domani' : F::longDate($date))) . ' alle ' . substr((string) $milestone->scheduledAt, 11, 5);
            $milestoneStep = null;
            foreach ($tasks as $task) {
                $step = $task->sourceStepId !== null ? ($steps[(int) $task->sourceStepId] ?? null) : null;
                if ($step !== null && (int) $step->milestoneId === (int) $milestone->milestoneId) {
                    $milestoneStep = $task;
                }
            }

            if ($soon['enabled'] && $daysTo <= (int) $soon['days']) {
                $prep = $milestoneStep !== null ? $this->followingChecklist($milestoneStep, $tasks) : null;
                // A chi tocca: la prima fase da fare dopo la data (es. la presentazione al cliente, di Sara).
                // (Se e' gia' tutto fatto, la fase subito dopo la data.)
                $whoTask = null;
                $nextAny = null;
                foreach ($tasks as $task) {
                    if ($milestoneStep === null || (int) $task->position <= (int) $milestoneStep->position || $task->stage === 'annullata') {
                        continue;
                    }
                    $nextAny ??= $task;
                    if ($whoTask === null && $task->isOpen()) {
                        $whoTask = $task;
                    }
                }
                $whoTask ??= $nextAny;
                // Il promemoria va a chi tocca e al responsabile del progetto, non a tutta la squadra.
                $recipients = array_values(array_unique(array_filter([
                    $whoTask?->assignedUserId !== null ? (int) $whoTask->assignedUserId : null,
                    $order->ownerUserId !== null ? (int) $order->ownerUserId : null,
                ])));
                if ($recipients === []) {
                    $recipients = $members;
                }
                foreach ($recipients as $userId) {
                    $items[] = ['when' => ucfirst($when), 'whoTaskId' => $whoTask?->id] + $this->item('milestone_soon', $userId, "{$milestone->label} {$when}",
                        $prep !== null ? "Da preparare: {$prep}" : 'Ricordati di prepararti.',
                        '/commesse/' . $order->id, 'work_order_milestones', (int) $milestone->id, "milestone_soon:{$milestone->id}:{$milestone->scheduledAt}", $ctx);
                }
            }

            if ($missing['enabled'] && $milestoneStep !== null && $daysTo <= (int) $missing['days']) {
                foreach ($tasks as $task) {
                    $step = $task->sourceStepId !== null ? ($steps[(int) $task->sourceStepId] ?? null) : null;
                    if (!$task->isOpen() || $task->assignedUserId === null || (int) $task->position >= (int) $milestoneStep->position
                        || ($step !== null && in_array($step->kind, [ProcessService::KIND_MILESTONE, ProcessService::KIND_APPROVAL], true))
                        || ($step !== null && (int) $step->isOptional === 1)) {
                        continue;
                    }
                    $items[] = $this->item('work_missing', (int) $task->assignedUserId, "\u{ab}{$task->title}\u{bb} non e' ancora fatta",
                        "{$milestone->label} e' {$when}.", '/attivita/' . $task->id, 'tasks', (int) $task->id, "work_missing:{$task->id}:{$milestone->scheduledAt}", $ctx, [
                            ['label' => 'Fatta', 'action' => '/attivita/' . $task->id . '/fatta', 'variant' => 'secondary', 'icon' => 'check'],
                        ]);
                }
            }
        }
    }

    private function followUpItems(array &$items, WorkOrder $order, ?Task $next, array $ctx): void
    {
        $who = $next?->assignedUserId !== null ? (int) $next->assignedUserId : ($order->ownerUserId !== null ? (int) $order->ownerUserId : null);
        if ($who === null) {
            return;
        }

        $rule = $this->rule('new_contact');
        if ($rule['enabled'] && $order->stage === 'contatto') {
            $days = F::daysFrom($this->today, substr((string) $order->createdAt, 0, 10));
            $hasMeeting = in_array('appointments', PackageCatalogService::installedNames(), true)
                && $this->container->get(\App\Repository\AppointmentRepository::class)->findForEntity('work_orders', (int) $order->id) !== [];
            if ($days >= (int) $rule['days'] && !$hasMeeting) {
                $items[] = $this->item('new_contact', $who, 'Fissa l\'incontro con il cliente', 'Ci ha contattato ' . F::relativeDays(substr((string) $order->createdAt, 0, 10), $this->today) . ' e non c\'e\' ancora un appuntamento.',
                    '/appuntamenti/nuovo?entity_type=work_orders&entity_id=' . $order->id . '&customer_id=' . $order->customerId, 'work_orders', (int) $order->id, 'new_contact:' . $order->id, $ctx, [
                        ['label' => 'Fissa l\'incontro', 'href' => '/appuntamenti/nuovo?entity_type=work_orders&entity_id=' . $order->id . '&customer_id=' . $order->customerId . '&kind=' . rawurlencode('incontro dal cliente'), 'variant' => 'primary', 'icon' => 'calendar'],
                    ]);
            }
        }

        $rule = $this->rule('quote_no_answer');
        if ($rule['enabled'] && $order->quoteId !== null && in_array('quotes', PackageCatalogService::installedNames(), true)) {
            $quote = $this->container->get(\App\Repository\QuoteRepository::class)->find((int) $order->quoteId);
            if ($quote !== null && $quote->stage === 'inviato' && $quote->sentAt !== null) {
                $days = F::daysFrom($this->today, substr((string) $quote->sentAt, 0, 10));
                if ($days >= (int) $rule['days']) {
                    $owner = $quote->ownerUserId !== null ? (int) $quote->ownerUserId : $who;
                    $items[] = ['when' => 'Da ' . F::plural($days, 'giorno', 'giorni')] + $this->item('quote_no_answer', $owner, 'Risenti il cliente sul preventivo', 'Inviato ' . F::plural($days, 'giorno', 'giorni') . ' fa, nessuna risposta.',
                        '/preventivi/' . $quote->id, 'work_orders', (int) $order->id, 'quote_no_answer:' . $quote->id . ':' . substr((string) $quote->sentAt, 0, 10), $ctx, [], true,
                        ['kind' => 'quote', 'what' => 'il preventivo'], $rule['autonomy']);
                }
            }
        }

        $rule = $this->rule('client_no_answer');
        if ($rule['enabled'] && $order->stage === 'in attesa del cliente' && $order->waitingSince !== null) {
            $since = max((string) $order->waitingSince, substr((string) ($order->lastReminderAt ?? ''), 0, 10));
            $days = F::daysFrom($this->today, $since);
            if ($days >= (int) $rule['days']) {
                $items[] = $this->item('client_no_answer', $who, 'Sollecita il cliente', 'Aspettiamo ' . ($order->waitingReason ?: 'una risposta') . ' da ' . F::plural($days, 'giorno', 'giorni') . '.',
                    '/commesse/' . $order->id, 'work_orders', (int) $order->id, 'client_no_answer:' . $order->id . ':' . $since, $ctx, [], true,
                    ['kind' => 'client', 'what' => mb_strtolower((string) ($order->waitingReason ?: 'il vostro riscontro'))], $rule['autonomy']);
            }
        }

        $rule = $this->rule('project_stale');
        if ($rule['enabled'] && !in_array($order->stage, ['in attesa del cliente', 'in pausa'], true) && $order->lastActivityAt !== null) {
            $days = F::daysFrom($this->today, substr((string) $order->lastActivityAt, 0, 10));
            if ($days >= (int) $rule['days']) {
                foreach (array_unique(array_filter([$order->ownerUserId !== null ? (int) $order->ownerUserId : null, $who])) as $userId) {
                    $items[] = $this->item('project_stale', $userId, 'Progetto fermo', 'Nessun movimento da ' . F::plural($days, 'giorno', 'giorni') . ($next !== null ? ": tocca a \u{ab}{$next->title}\u{bb}." : '.'),
                        '/commesse/' . $order->id, 'work_orders', (int) $order->id, 'project_stale:' . $order->id . ':' . substr((string) $order->lastActivityAt, 0, 10), $ctx);
                }
            }
        }
    }

    /** @param Task[] $tasks */
    private function portfolioItem(array &$items, WorkOrder $order, array $tasks, array $ctx): void
    {
        $rule = $this->rule('portfolio');
        if (!$rule['enabled'] || $order->deliveredAt === null || F::daysFrom($this->today, (string) $order->deliveredAt) > (int) $rule['days']) {
            return;
        }
        $last = null;
        foreach ($tasks as $task) {
            if ($task->assignedUserId !== null) {
                $last = $task;
            }
        }
        $who = $last?->assignedUserId !== null ? (int) $last->assignedUserId : ($order->ownerUserId !== null ? (int) $order->ownerUserId : null);
        if ($who === null) {
            return;
        }
        $customer = $order->customerId !== null ? $this->container->get(CustomerRepository::class)->find((int) $order->customerId) : null;
        $items[] = $this->item('portfolio', $who, 'Un\'immagine per il portfolio', $this->container->get(AssistantWriter::class)->portfolioIdea($order, $customer instanceof Customer ? $customer : null),
            '/commesse/' . $order->id, 'work_orders', (int) $order->id, 'portfolio:' . $order->id, $ctx);
    }

    /**
     * Le proprie attivita' con la data passata (non gia' mostrate come "tocca a te").
     *
     * @param array<int,bool> $shown
     */
    private function lateTasks(int $userId, array $shown): array
    {
        if (!$this->rule('task_late')['enabled'] || !in_array('tasks', PackageCatalogService::installedNames(), true)) {
            return [];
        }
        $items = [];
        foreach ($this->container->get(TaskRepository::class)->findForBoard($userId, $this->today . ' 00:00:00') as $task) {
            $day = $task->day();
            if (!$task->isOpen() || isset($shown[(int) $task->id]) || $day === null || $day >= $this->today || (int) $task->waitsForClient === 1) {
                continue;
            }
            $items[] = $this->item('task_late', $userId, (string) $task->title, 'Era per ' . F::relativeDays($day, $this->today) . '.', '/attivita/' . $task->id, 'tasks', (int) $task->id, 'task_late:' . $task->id,
                ['orderId' => $task->entityType === WorkOrderService::ENTITY ? (int) $task->entityId : null, 'context' => null, 'contextHref' => null], [
                    ['label' => 'Fatta', 'action' => '/attivita/' . $task->id . '/fatta', 'variant' => 'secondary', 'icon' => 'check'],
                ]);
        }

        return $items;
    }

    private function item(string $rule, int $user, string $title, string $why, ?string $href, ?string $entityType, ?int $entityId, string $dedupe, array $ctx, array $actions = [], ?bool $notify = null, ?array $proposal = null, ?string $autonomy = null): array
    {
        $meta = AssistantRules::ALL[$rule];

        return [
            'rule' => $rule,
            'user' => $user,
            'section' => $meta['section'],
            'title' => $title,
            'why' => $why,
            'href' => $href,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'dedupe' => $dedupe,
            'notify' => $notify ?? $meta['notify'],
            'actions' => $actions,
            'autonomy' => $autonomy,
        ] + ($proposal !== null ? ['proposal' => $proposal] : []) + $ctx;
    }

    /** Perche' tocca a te: la fase prima e' stata chiusa (da chi e quando). */
    private function stepWhy(Task $task, array $tasks): string
    {
        $previous = null;
        foreach ($tasks as $t) {
            if ((int) $t->id === (int) $task->id) {
                break;
            }
            if (!$t->isOpen()) {
                $previous = $t;
            }
        }
        $parts = [];
        if ($previous !== null && $previous->completedAt !== null) {
            $parts[] = "\u{ab}{$previous->title}\u{bb} e' fatta da " . F::relativeDays(substr((string) $previous->completedAt, 0, 10), $this->today) . '.';
        }
        $progress = $task->checklistProgress();
        if ($progress['total'] > 0) {
            $parts[] = "Checklist {$progress['done']}/{$progress['total']}.";
        }

        return $parts !== [] ? implode(' ', $parts) : 'E\' la prossima fase del progetto.';
    }

    /** La checklist della prima fase di lavoro dopo quella della data (es. le domande del briefing). */
    private function followingChecklist(Task $milestoneTask, array $tasks): ?string
    {
        $after = false;
        foreach ($tasks as $task) {
            if ((int) $task->id === (int) $milestoneTask->id) {
                $after = true;
                continue;
            }
            if ($after && trim((string) $task->checklist) !== '') {
                $lines = array_values(array_filter(array_map(static fn (string $l) => trim(preg_replace('/^\[x\]\s*/i', '', $l)), explode("\n", (string) $task->checklist))));

                return implode('; ', $lines) . '.';
            }
        }

        return null;
    }

    private function previousDoneAt(Task $task, array $tasks): ?string
    {
        $date = null;
        foreach ($tasks as $t) {
            if ((int) $t->id === (int) $task->id) {
                break;
            }
            if (!$t->isOpen() && $t->completedAt !== null) {
                $date = substr((string) $t->completedAt, 0, 10);
            }
        }

        return $date;
    }

    /** @return array<int, WorkOrderTypeStep> */
    private function steps(?int $typeId): array
    {
        if ($typeId === null) {
            return [];
        }
        if (!isset($this->stepsByType[$typeId])) {
            $this->stepsByType[$typeId] = [];
            foreach ($this->container->get(WorkOrderTypeStepRepository::class)->findByType($typeId) as $step) {
                $this->stepsByType[$typeId][(int) $step->id] = $step;
            }
        }

        return $this->stepsByType[$typeId];
    }

    /** @param WorkOrder[] $orders @return array<int,string> */
    private function customerNames(array $orders): array
    {
        $names = [];
        if (!in_array('customers', PackageCatalogService::installedNames(), true)) {
            return $names;
        }
        $repo = $this->container->get(CustomerRepository::class);
        foreach (array_unique(array_filter(array_map(static fn (WorkOrder $o) => $o->customerId, $orders))) as $id) {
            $customer = $repo->find((int) $id);
            if ($customer instanceof Customer) {
                $names[(int) $id] = $customer->displayName();
            }
        }

        return $names;
    }

    /** @return array<int,string> */
    private function userNames(): array
    {
        if ($this->userNames === null) {
            $this->userNames = [];
            foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
                $this->userNames[(int) $user->id] = trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username;
            }
        }

        return $this->userNames;
    }

    public function currentUserFirstName(): string
    {
        $id = (int) $this->container->get(AuthService::class)->currentUserId();
        $user = $this->container->get(UserRepository::class)->find($id);

        return $user !== null && trim((string) $user->firstName) !== '' ? (string) $user->firstName : '';
    }
}
