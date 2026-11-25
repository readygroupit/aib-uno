<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Core\Container;
use App\Knowledge\KnowledgeService;
use App\Model\Customer;
use App\Model\User;
use App\Model\WorkOrder;
use App\Notifications\NotificationService;
use App\Notifications\NotificationTypes;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\CustomerTeamMemberRepository;
use App\Repository\CustomerTouchpointRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Service\AuthService;

/**
 * Le richieste che chiedono di fare piu' cose insieme ("sono stato con
 * @alessio da Della Rocca, gli e' piaciuta l'idea del bollino: mettila
 * nella roadmap condivisa e coinvolgi @valentina"). L'IA scrive i passi
 * (strumento plan_actions); qui i nomi diventano clienti, progetti e
 * persone, il piano si mostra per intero e solo dopo la conferma si
 * esegue. Niente parte verso i clienti: solo cose interne.
 *
 * Il piano confermato e' quello risolto e tenuto in sessione, non quello
 * che rimanda il browser.
 */
final class ActionPlanService
{
    public const ACTIONS = ['add_note', 'log_contact', 'notify', 'add_to_team', 'create_task'];

    private const PERMISSIONS = [
        'add_note' => 'client_knowledge.create',
        'log_contact' => 'client_health.create',
        'notify' => null,
        'add_to_team' => 'customers.edit',
        'create_task' => 'tasks.create',
    ];

    private const PACKAGES = [
        'add_note' => 'client_knowledge',
        'log_contact' => 'client_health',
        'notify' => 'notifications',
        'add_to_team' => 'customers',
        'create_task' => 'tasks',
    ];

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * I passi dell'IA con i nomi risolti, pronti da mostrare.
     *
     * @param list<array<string,mixed>> $steps
     * @return array{id:string, summary:string, steps:list<array<string,mixed>>, problems:list<string>}
     */
    public function prepare(string $summary, array $steps): array
    {
        $resolved = [];
        $problems = [];
        $installed = PackageCatalogService::installedNames();
        $auth = $this->container->get(AuthService::class);
        $lastCustomer = null;

        foreach ($steps as $index => $step) {
            $action = (string) ($step['action'] ?? '');
            $n = $index + 1;
            if (!in_array($action, self::ACTIONS, true) || !in_array(self::PACKAGES[$action], $installed, true)) {
                $problems[] = "Passo {$n}: non so ancora farlo ({$action}).";
                continue;
            }
            if (self::PERMISSIONS[$action] !== null && !$auth->hasPermission(self::PERMISSIONS[$action])) {
                $problems[] = "Passo {$n}: non hai il permesso.";
                continue;
            }

            $customer = isset($step['customer']) && trim((string) $step['customer']) !== '' ? $this->customer((string) $step['customer']) : $lastCustomer;
            if (isset($step['customer']) && trim((string) $step['customer']) !== '' && $customer === null) {
                $problems[] = "Passo {$n}: non trovo il cliente \u{ab}{$step['customer']}\u{bb}.";
            }
            $lastCustomer = $customer ?? $lastCustomer;
            $order = $customer !== null ? $this->project($customer, (string) ($step['project'] ?? '')) : null;

            $people = [];
            foreach ((array) ($step['people'] ?? []) as $name) {
                $user = $this->person((string) $name);
                if ($user === null) {
                    $problems[] = "Passo {$n}: non trovo \u{ab}{$name}\u{bb} tra i colleghi.";
                    continue;
                }
                $people[(int) $user->id] = trim("{$user->firstName} {$user->lastName}");
            }

            $needsCustomer = in_array($action, ['add_note', 'log_contact', 'add_to_team'], true);
            if ($needsCustomer && $customer === null) {
                $problems[] = "Passo {$n}: serve un cliente.";
                continue;
            }

            $resolved[] = [
                'action' => $action,
                'customerId' => $customer?->id,
                'customer' => $customer?->displayName(),
                'workOrderId' => $order?->id,
                'project' => $order?->title,
                'people' => $people,
                'title' => trim((string) ($step['title'] ?? '')),
                'body' => trim((string) ($step['body'] ?? '')),
                'kind' => in_array($step['kind'] ?? null, ['idea', 'decisione', 'preferenza', 'nota'], true) ? $step['kind'] : 'nota',
                'roadmap' => !empty($step['roadmap']),
                'roadmapStatus' => in_array($step['roadmap_status'] ?? null, ['da valutare', 'piaciuta al cliente', 'approvata', 'in corso', 'fatta'], true) ? $step['roadmap_status'] : 'da valutare',
                'shareWithTeam' => !empty($step['share_with_team']) || !empty($step['roadmap']),
                'date' => $this->date((string) ($step['date'] ?? '')) ?? date('Y-m-d'),
                'channel' => in_array($step['channel'] ?? null, ['telefono', 'whatsapp', 'email', 'incontro', 'videochiamata'], true) ? $step['channel'] : 'incontro',
                'mood' => in_array($step['mood'] ?? null, ['contento', 'neutro', 'freddo', 'scontento'], true) ? $step['mood'] : 'neutro',
                'nextStep' => trim((string) ($step['next_step'] ?? '')),
                'nextDate' => $this->date((string) ($step['next_date'] ?? '')),
                'role' => in_array($step['role'] ?? null, ['referente', 'social', 'grafica', 'video', 'sviluppo', 'adv', 'strategia', 'altro'], true) ? $step['role'] : 'altro',
                'message' => trim((string) ($step['message'] ?? '')),
            ];
        }

        $id = bin2hex(random_bytes(8));
        $_SESSION['action_plans'][$id] = ['summary' => $summary, 'steps' => $resolved, 'created' => time()];
        // Solo gli ultimi piani: la sessione non cresce all'infinito.
        $_SESSION['action_plans'] = array_slice($_SESSION['action_plans'], -10, null, true);

        return ['id' => $id, 'summary' => $summary, 'steps' => $resolved, 'problems' => $problems];
    }

    /** Il passo in una frase, per farlo leggere prima della conferma. */
    public function describe(array $step): string
    {
        $who = $step['people'] !== [] ? implode(', ', $step['people']) : '';
        $where = $step['customer'] . ($step['project'] !== null ? " (progetto \u{ab}{$step['project']}\u{bb})" : '');

        return match ($step['action']) {
            'add_note' => ($step['roadmap'] ? "Nella roadmap di {$where}, stato \u{ab}{$step['roadmapStatus']}\u{bb}: " : "Nel diario di {$where} ({$step['kind']}): ")
                . "\u{ab}{$step['title']}\u{bb}"
                . ($step['shareWithTeam'] ? ', condivisa con la squadra' : '')
                . ($who !== '' ? ", coinvolge {$who}" : ''),
            'log_contact' => "Registro il contatto con {$where} del " . date('d/m/Y', strtotime($step['date'])) . " ({$step['channel']})" . ($who !== '' ? " con {$who}" : '') . ': ' . $step['body']
                . ($step['nextStep'] !== '' ? ". Prossimo passo: {$step['nextStep']}" . ($step['nextDate'] !== null ? ' entro il ' . date('d/m/Y', strtotime($step['nextDate'])) : '') : ''),
            'notify' => "Avviso {$who}: " . ($step['message'] !== '' ? $step['message'] : $step['title']),
            'add_to_team' => "Metto {$who} nella squadra di {$step['customer']} ({$step['role']})",
            'create_task' => "Attivita' \u{ab}{$step['title']}\u{bb}" . ($who !== '' ? " per {$who}" : '') . ' entro il ' . date('d/m/Y', strtotime($step['date'])) . ($step['customer'] !== null ? " ({$where})" : ''),
            default => $step['action'],
        };
    }

    /** @return array{ok:bool, message:string, open:?string} */
    public function execute(string $planId): array
    {
        $plan = $_SESSION['action_plans'][$planId] ?? null;
        if ($plan === null) {
            return ['ok' => false, 'message' => 'Piano non trovato o gia\' eseguito.', 'open' => null];
        }
        unset($_SESSION['action_plans'][$planId]);

        $done = [];
        $open = null;
        $me = (int) $this->container->get(AuthService::class)->currentUserId();
        foreach ($plan['steps'] as $step) {
            $people = array_map('intval', array_keys($step['people']));
            switch ($step['action']) {
                case 'add_note':
                    $this->container->get(KnowledgeService::class)->addNote([
                        'customer_id' => $step['customerId'],
                        'work_order_id' => $step['workOrderId'],
                        'kind' => $step['kind'],
                        'title' => mb_substr($step['title'] !== '' ? $step['title'] : 'Nota', 0, 200),
                        'body' => $step['body'] !== '' ? $step['body'] : null,
                        'noted_at' => $step['date'],
                        'source' => 'assistente',
                        'on_roadmap' => $step['roadmap'] ? 1 : 0,
                        'roadmap_status' => $step['roadmap'] ? $step['roadmapStatus'] : null,
                        'mentioned_user_ids' => $people !== [] ? implode(',', $people) : null,
                    ], $step['shareWithTeam']);
                    $done[] = $step['roadmap'] ? 'idea nella roadmap' : 'nota nel diario';
                    break;
                case 'log_contact':
                    $this->container->get(CustomerTouchpointRepository::class)->insert([
                        'customer_id' => $step['customerId'],
                        'contact_date' => $step['date'],
                        'channel' => $step['channel'],
                        'user_id' => $me ?: null,
                        'mood' => $step['mood'],
                        'summary' => mb_substr(($step['body'] !== '' ? $step['body'] : $step['title']) . ($step['people'] !== [] ? ' (con ' . implode(', ', $step['people']) . ')' : ''), 0, 1000),
                        'next_step' => $step['nextStep'] !== '' ? $step['nextStep'] : null,
                        'next_date' => $step['nextDate'],
                    ]);
                    $this->container->get(\App\Customers\CustomerFollowUpService::class)->contacted((int) $step['customerId'], $step['nextDate'], $step['nextStep'] !== '' ? $step['nextStep'] : null);
                    $done[] = 'contatto registrato';
                    break;
                case 'notify':
                    foreach ($people as $userId) {
                        $this->container->get(NotificationService::class)->notify(
                            $userId,
                            NotificationTypes::MENTION,
                            $step['title'] !== '' ? $step['title'] : 'Ti coinvolgono' . ($step['customer'] !== null ? " su {$step['customer']}" : ''),
                            $step['message'] !== '' ? $step['message'] : $step['body'],
                            $step['workOrderId'] !== null ? '/commesse/' . $step['workOrderId'] : ($step['customerId'] !== null ? '/clienti/' . $step['customerId'] : null),
                            $step['customerId'] !== null ? 'customers' : null,
                            $step['customerId'] !== null ? (int) $step['customerId'] : null
                        );
                    }
                    $done[] = count($people) === 1 ? '1 persona avvisata' : count($people) . ' persone avvisate';
                    break;
                case 'add_to_team':
                    $team = $this->container->get(CustomerTeamMemberRepository::class);
                    $already = array_map(static fn ($m) => (int) $m->userId, $team->findByCustomer((int) $step['customerId']));
                    foreach ($people as $userId) {
                        if (!in_array($userId, $already, true)) {
                            $team->insert(['customer_id' => $step['customerId'], 'user_id' => $userId, 'role' => $step['role']]);
                        }
                    }
                    $done[] = 'squadra aggiornata';
                    break;
                case 'create_task':
                    $assignees = $people !== [] ? $people : [$me];
                    foreach ($assignees as $userId) {
                        $taskId = $this->container->get(TaskRepository::class)->insert([
                            'title' => mb_substr($step['title'] !== '' ? $step['title'] : 'Da fare', 0, 200),
                            'description' => $step['body'] !== '' ? $step['body'] : null,
                            'stage' => 'da fare',
                            'priority' => 'normale',
                            'planned_date' => $step['date'],
                            'assigned_user_id' => $userId,
                            'entity_type' => $step['workOrderId'] !== null ? 'work_orders' : ($step['customerId'] !== null ? 'customers' : null),
                            'entity_id' => $step['workOrderId'] ?? $step['customerId'],
                        ]);
                        $this->container->get(NotificationService::class)->notify($userId, NotificationTypes::TASK_ASSIGNED, "Nuova attivita' per te: {$step['title']}", null, '/attivita/' . $taskId, 'tasks', $taskId);
                    }
                    $done[] = count($assignees) === 1 ? "1 attivita'" : count($assignees) . " attivita'";
                    break;
            }
            $open ??= $step['workOrderId'] !== null ? '/commesse/' . $step['workOrderId'] : ($step['customerId'] !== null ? '/clienti/' . $step['customerId'] : null);
        }

        return ['ok' => true, 'message' => $done !== [] ? 'Fatto: ' . implode(', ', array_unique($done)) . '.' : 'Niente da fare.', 'open' => $open];
    }

    public function cancel(string $planId): void
    {
        unset($_SESSION['action_plans'][$planId]);
    }

    private function customer(string $name): ?Customer
    {
        if (!in_array('customers', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $repo = $this->container->get(CustomerRepository::class);
        $matches = $repo->searchFreeText(['company_name', 'first_name', 'last_name'], trim($name), 5, ['company_name']);
        if (count($matches) === 1) {
            return $matches[0];
        }
        foreach ($matches as $match) {
            if (strcasecmp($match->displayName(), trim($name)) === 0) {
                return $match;
            }
        }

        return $matches[0] ?? null;
    }

    /** Il progetto nominato, altrimenti l'unico aperto del cliente. */
    private function project(Customer $customer, string $title): ?WorkOrder
    {
        if (!in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $open = array_values(array_filter($this->container->get(WorkOrderRepository::class)->findByCustomer((int) $customer->id), static fn (WorkOrder $o) => $o->isActive()));
        if (trim($title) !== '') {
            foreach ($open as $order) {
                if (stripos((string) $order->title, trim($title)) !== false) {
                    return $order;
                }
            }
        }

        return count($open) === 1 ? $open[0] : null;
    }

    /** "@alessio.scicolone", "Alessio Scicolone", "Valentina": il collega, se e' uno solo. */
    private function person(string $name): ?User
    {
        $name = trim(ltrim(trim($name), '@'));
        if ($name === '') {
            return null;
        }
        $users = $this->container->get(UserRepository::class)->findAll();
        $norm = static fn (string $s) => mb_strtolower(trim(preg_replace('/[\s._-]+/', ' ', $s)));
        $wanted = $norm($name);
        // "@valentinaconetta" scritto attaccato vale come "valentina.conetta".
        $compact = static fn (string $s) => str_replace(' ', '', $s);
        $byFirst = [];
        foreach ($users as $user) {
            $full = $norm("{$user->firstName} {$user->lastName}");
            $username = $norm((string) $user->username);
            if ($username === $wanted || $full === $wanted || $compact($username) === $compact($wanted) || ($compact($full) === $compact($wanted) && trim((string) $user->lastName) !== '')) {
                return $user;
            }
            if ($norm((string) $user->firstName) === $wanted) {
                $byFirst[] = $user;
            }
        }

        return count($byFirst) === 1 ? $byFirst[0] : null;
    }

    private function date(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $time = strtotime($text);

        return $time !== false ? date('Y-m-d', $time) : null;
    }
}
