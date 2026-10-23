<?php

declare(strict_types=1);

namespace App\Planner;

use App\Core\Container;
use App\Model\User;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Support\ItalianFormat as F;
use App\Work\WorkOrderOverviewService;

/**
 * Le tre viste dell'organizer, gia' pronte da mostrare:
 * - la giornata di una persona: il piano (DayPlanner) piu' quello che deve
 *   sapere (consegne vicine, uscite di domani, sovrapposizioni, commesse
 *   ferme, uscite nella stessa zona), cosa ha gia' fatto, cosa fare se
 *   avanza tempo;
 * - il team oggi: carico di ognuno, cosa sta facendo adesso, e le
 *   proposte per riequilibrare (da chi e' pieno a chi e' libero);
 * - la settimana: il carico di ognuno giorno per giorno.
 */
final class PlannerOverviewService
{
    private const GREETINGS = [5 => 'Buongiorno', 13 => 'Buon pomeriggio', 18 => 'Buonasera'];

    private DayPlanner $planner;
    private UserRepository $users;
    private TaskRepository $tasks;
    private ?WorkOrderRepository $orders = null;
    private ?CustomerRepository $customers = null;
    private string $today;

    public function __construct(Container $container)
    {
        $this->planner = $container->get(DayPlanner::class);
        $this->users = $container->get(UserRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
        $installed = PackageCatalogService::installedNames();
        if (in_array('work_orders', $installed, true)) {
            $this->orders = $container->get(WorkOrderRepository::class);
        }
        if (in_array('customers', $installed, true)) {
            $this->customers = $container->get(CustomerRepository::class);
        }
        $this->today = date('Y-m-d');
    }

    public function myDay(int $userId, string $date): array
    {
        $plan = $this->planner->plan($userId, $date);
        $user = $this->users->find($userId);
        $name = $user instanceof User ? (trim((string) $user->firstName) ?: (string) $user->username) : '';

        $doneToday = [];
        if ($date === $this->today) {
            foreach ($this->tasks->findForBoard($userId, $this->today . ' 00:00:00') as $task) {
                if ($task->stage === 'fatta') {
                    $doneToday[] = ['id' => (int) $task->id, 'title' => (string) $task->title, 'href' => '/attivita/' . $task->id, 'action' => '/attivita/' . $task->id . '/fatta'];
                }
            }
        }

        $upcoming = [];
        if ($plan['freeMinutes'] >= 30) {
            $next = $this->planner->plan($userId, $plan['nextWorkday'], 0);
            $firstParts = array_filter($next['placed'], static fn (array $i) => $i['kind'] === 'task' && ($i['part'] === null || str_starts_with((string) $i['part'], '1/')));
            foreach (array_slice(array_values($firstParts), 0, 3) as $item) {
                if ($item['minutes'] <= $plan['freeMinutes']) {
                    $upcoming[] = ['id' => $item['id'], 'title' => $item['title'], 'context' => $item['context'], 'href' => $item['href'], 'duration' => WorkOrderOverviewService::duration($item['minutes']), 'action' => $item['action']];
                }
            }
        }

        return [
            'name' => $name,
            'greeting' => $this->greeting($name, $plan),
            'dateLabel' => ucfirst($this->planner->dayName($date)),
            'plan' => $plan,
            'alerts' => $this->alerts($userId, $date, $plan),
            'doneToday' => $doneToday,
            'upcoming' => $upcoming,
            'evening' => $date === $this->today && (int) date('G') >= 16,
        ];
    }

    /** Il team oggi: una riga per persona e le proposte per riequilibrare. */
    public function team(string $date): array
    {
        $people = [];
        $nowMinute = (int) date('G') * 60 + (int) date('i');
        foreach ($this->staff() as $user) {
            $plan = $this->planner->plan((int) $user->id, $date);
            $now = null;
            $next = null;
            foreach ($plan['timeline'] as $row) {
                if (in_array($row['type'], ['free', 'travel'], true)) {
                    continue;
                }
                if ($date === $this->today && $row['start'] <= $nowMinute && $row['end'] > $nowMinute && $now === null) {
                    $now = $row;
                } elseif ($row['start'] >= $nowMinute && $next === null && $row['type'] !== 'break') {
                    $next = $row;
                }
            }
            $name = trim((string) $user->firstName) ?: (string) $user->username;
            $people[] = [
                'id' => (int) $user->id,
                'name' => $name,
                'initials' => F::initials(trim("{$user->firstName} {$user->lastName}") ?: $name),
                'href' => '/oggi?persona=' . $user->id . ($date !== $this->today ? '&giorno=' . $date : ''),
                'working' => $plan['working'],
                'absence' => $plan['absence'],
                'load' => $plan['loadPercent'],
                'loadTone' => $plan['loadPercent'] > 105 ? 'danger' : ($plan['loadPercent'] >= 80 ? 'warning' : 'success'),
                'planned' => WorkOrderOverviewService::duration($plan['plannedMinutes'] + $plan['appointmentMinutes']),
                'capacity' => WorkOrderOverviewService::duration($plan['capacityMinutes']),
                'free' => $plan['freeMinutes'],
                'freeLabel' => $plan['freeMinutes'] >= 30 ? WorkOrderOverviewService::duration($plan['freeMinutes']) . ' liberi' : null,
                'overflow' => $plan['overflow'],
                'overflowLabel' => $plan['overflowMinutes'] > 0 ? WorkOrderOverviewService::duration($plan['overflowMinutes']) . ' non ci stanno' : null,
                'late' => $plan['lateCount'],
                'appointments' => $plan['appointments'],
                'now' => $now !== null ? ['title' => $now['title'], 'until' => self::untilLabel($now['end'])] : null,
                'next' => $next !== null ? ['title' => $next['title'], 'time' => DayPlanner::clock($next['start'])] : null,
            ];
        }

        return ['people' => $people, 'suggestions' => $this->rebalance($people)];
    }

    /** Carico di ognuno nei giorni lavorativi della settimana. */
    public function week(string $monday): array
    {
        $days = [];
        for ($i = 0; $i < 5; $i++) {
            $date = date('Y-m-d', strtotime("{$monday} +{$i} days"));
            $days[] = ['date' => $date, 'label' => ucfirst(substr($this->planner->dayName($date), 0, 3)) . ' ' . (int) date('j', strtotime($date)), 'isToday' => $date === $this->today, 'isPast' => $date < $this->today];
        }

        $rows = [];
        foreach ($this->staff() as $user) {
            $cells = [];
            foreach ($days as $day) {
                if ($day['isPast']) {
                    $cells[] = ['date' => $day['date'], 'past' => true];
                    continue;
                }
                // Per i giorni futuri si guarda la giornata intera, non da "adesso".
                $plan = $this->planner->plan((int) $user->id, $day['date'], 0);
                $cells[] = [
                    'date' => $day['date'],
                    'past' => false,
                    'working' => $plan['working'],
                    'load' => $plan['loadPercent'],
                    'tone' => !$plan['working'] ? 'off' : ($plan['loadPercent'] > 105 ? 'danger' : ($plan['loadPercent'] >= 80 ? 'warning' : ($plan['loadPercent'] >= 40 ? 'ok' : 'light'))),
                    'label' => $plan['working'] ? $plan['loadPercent'] . '%' : ($plan['absence'] !== null ? mb_strtolower($plan['absence']) : 'riposo'),
                    'detail' => $plan['appointments'] . ' app. · ' . WorkOrderOverviewService::duration($plan['plannedMinutes'] + $plan['overflowMinutes']) . ' di lavoro',
                    'href' => '/oggi?persona=' . $user->id . '&giorno=' . $day['date'],
                ];
            }
            $name = trim((string) $user->firstName) ?: (string) $user->username;
            $rows[] = ['id' => (int) $user->id, 'name' => $name, 'initials' => F::initials(trim("{$user->firstName} {$user->lastName}") ?: $name), 'cells' => $cells];
        }

        return ['days' => $days, 'rows' => $rows];
    }

    private function greeting(string $name, array $plan): array
    {
        $hour = (int) date('G');
        $salute = 'Buongiorno';
        foreach (self::GREETINGS as $from => $text) {
            if ($hour >= $from) {
                $salute = $text;
            }
        }

        if (!$plan['working']) {
            return ['title' => $plan['isToday'] ? "{$salute}, {$name}" : $name, 'text' => 'Giorno di riposo' . ($plan['appointments'] > 0 ? ', ma ci sono appuntamenti in calendario.' : '.')];
        }

        $work = WorkOrderOverviewService::duration($plan['plannedMinutes'] + $plan['overflowMinutes']);

        // Dopo l'orario: non un piano, il bilancio della giornata.
        if ($plan['isToday'] && date('H:i') >= $plan['hours']['end']) {
            $left = count($plan['overflow']);
            $movable = count(array_filter($plan['overflow'], static fn (array $i) => $i['movable']));
            $rest = $movable > 0 ? ": le sposto a {$plan['nextWorkdayLabel']} con un clic." : ': domattina e\' la prima cosa in scaletta.';

            return ['title' => "{$salute}, {$name}", 'text' => $left > 0
                ? "La giornata di lavoro e' finita. " . ($left === 1 ? "E' rimasta una cosa" : "Sono rimaste {$left} cose") . " (circa {$work}){$rest}"
                : "La giornata di lavoro e' finita e non e' rimasto niente. Bel lavoro."];
        }
        $parts = [];
        if ($plan['appointments'] > 0) {
            $parts[] = F::plural($plan['appointments'], 'appuntamento', 'appuntamenti');
        }
        $count = count(array_filter($plan['placed'], static fn (array $i) => $i['part'] === null || str_starts_with((string) $i['part'], '1/'))) + count($plan['overflow']);
        if ($count > 0) {
            $parts[] = F::plural($count, "cosa da fare", 'cose da fare') . " (circa {$work})";
        }
        $what = $parts !== [] ? implode(' e ', $parts) : 'niente in programma';
        $verb = $plan['isToday'] ? 'Oggi hai' : 'Hai';

        $text = "{$verb} {$what}.";
        if ($plan['overflowMinutes'] > 0) {
            $text .= ' Non ci sta tutto: ' . WorkOrderOverviewService::duration($plan['overflowMinutes']) . ' restano fuori, ti propongo di spostarle a ' . $plan['nextWorkdayLabel'] . '.';
        } elseif ($count > 0) {
            $text .= $plan['freeMinutes'] >= 60 ? ' Ci sta tutto, e resta ' . WorkOrderOverviewService::duration($plan['freeMinutes']) . ' di margine.' : ' Ci sta tutto, giusto giusto.';
        }
        if ($plan['lateCount'] > 0) {
            $text .= ' Prima recupera ' . ($plan['lateCount'] === 1 ? 'la cosa rimasta indietro' : "le {$plan['lateCount']} cose rimaste indietro") . '.';
        }

        return ['title' => $plan['isToday'] ? "{$salute}, {$name}" : $name, 'text' => $text];
    }

    /**
     * Quello che conviene sapere oggi, oltre al piano: consegne vicine,
     * uscite di domani, sovrapposizioni, commesse ferme, uscite nella stessa
     * zona in giorni diversi.
     */
    private function alerts(int $userId, string $date, array $plan): array
    {
        $alerts = [];

        foreach ($this->orders?->findAll(['owner_user_id' => $userId]) ?? [] as $order) {
            if (!$order->isActive() || $order->dueDate === null) {
                continue;
            }
            $days = F::daysFrom($order->dueDate, $date);
            if ($days < 0) {
                $alerts[] = ['tone' => 'danger', 'icon' => 'circle-exclamation', 'text' => "\u{ab}{$order->title}\u{bb} doveva essere consegnata " . F::relativeDays($order->dueDate, $date) . ': concorda una nuova data.', 'href' => '/commesse/' . $order->id];
            } elseif ($days <= 1) {
                $alerts[] = ['tone' => 'warning', 'icon' => 'calendar', 'text' => ($days === 0 ? 'Oggi' : 'Domani') . " va consegnata \u{ab}{$order->title}\u{bb}.", 'href' => '/commesse/' . $order->id];
            }
        }

        // Uscite del giorno dopo: si prepara oggi quello che serve.
        foreach ($this->planner->appointmentsFor($userId, $plan['nextWorkday']) as $a) {
            $away = in_array($a->kind, ['shooting', 'riprese', 'incontro dal cliente', 'sopralluogo', 'evento'], true);
            if (!$away) {
                continue;
            }
            $checklist = count(array_filter(array_map('trim', explode("\n", (string) $a->checklist))));
            $alerts[] = [
                'tone' => 'info',
                'icon' => 'location-dot',
                'text' => ucfirst($plan['nextWorkdayLabel']) . ' alle ' . date('H:i', strtotime((string) $a->startAt)) . ": \u{ab}{$a->title}\u{bb}" . ($a->location ? ' a ' . DayPlanner::place($a->location) : '') . ($checklist > 0 ? '. Da preparare: ' . F::plural($checklist, 'cosa', 'cose') . '.' : '.'),
                'href' => '/appuntamenti/' . $a->id,
            ];
        }

        // Sovrapposizioni nella giornata.
        $todayAppointments = $this->planner->appointmentsFor($userId, $date);
        foreach ($todayAppointments as $i => $a) {
            foreach (array_slice($todayAppointments, $i + 1) as $b) {
                $aStart = strtotime((string) $a->startAt) - 60 * (int) $a->travelMinutes;
                $aEnd = strtotime($a->end()) + 60 * (int) $a->travelMinutes;
                $bStart = strtotime((string) $b->startAt) - 60 * (int) $b->travelMinutes;
                $bEnd = strtotime($b->end()) + 60 * (int) $b->travelMinutes;
                if ($aStart < $bEnd && $bStart < $aEnd) {
                    $alerts[] = ['tone' => 'danger', 'icon' => 'circle-exclamation', 'text' => "\u{ab}{$a->title}\u{bb} e \u{ab}{$b->title}\u{bb} si sovrappongono: sposta uno dei due o fatti sostituire.", 'href' => '/appuntamenti/' . $b->id];
                }
            }
        }

        // Uscite della settimana nello stesso posto in giorni diversi.
        $monday = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $byPlace = [];
        for ($i = 0; $i < 5; $i++) {
            $day = date('Y-m-d', strtotime("{$monday} +{$i} days"));
            if ($day < $date) {
                continue;
            }
            foreach ($this->planner->appointmentsFor($userId, $day) as $a) {
                if ($a->location && (int) $a->travelMinutes >= 15) {
                    $byPlace[DayPlanner::place($a->location)][$day] = true;
                }
            }
        }
        foreach ($byPlace as $place => $days) {
            if (count($days) >= 2) {
                $labels = array_map(fn (string $d) => substr($this->planner->dayName($d), 0, 3), array_keys($days));
                $alerts[] = ['tone' => 'info', 'icon' => 'car', 'text' => "Questa settimana vai a {$place} " . count($days) . ' volte (' . implode(', ', $labels) . '): se il cliente puo\', raggruppa le uscite nello stesso giorno.', 'href' => '/appuntamenti'];
            }
        }

        return $alerts;
    }

    /**
     * Da chi non ci sta a chi ha tempo: per ogni lavoro rimasto fuori (che
     * si puo' spostare), la persona con piu' tempo libero se basta.
     */
    private function rebalance(array $people): array
    {
        $free = [];
        foreach ($people as $p) {
            if ($p['working'] && $p['free'] >= 30) {
                $free[$p['id']] = ['name' => $p['name'], 'minutes' => $p['free']];
            }
        }

        $suggestions = [];
        foreach ($people as $p) {
            foreach ($p['overflow'] as $item) {
                if (!$item['movable']) {
                    continue;
                }
                uasort($free, static fn (array $a, array $b) => $b['minutes'] <=> $a['minutes']);
                $targetId = array_key_first($free);
                if ($targetId === null || $targetId === $p['id'] || $free[$targetId]['minutes'] < $item['minutes']) {
                    continue;
                }
                $suggestions[] = [
                    'text' => "\u{ab}{$item['title']}\u{bb} ({$item['duration']}) non ci sta nella giornata di {$p['name']}: {$free[$targetId]['name']} ha " . WorkOrderOverviewService::duration($free[$targetId]['minutes']) . ' liberi.',
                    'action' => '/oggi/riassegna?task=' . $item['id'] . '&persona=' . $targetId,
                    'label' => 'Passala a ' . $free[$targetId]['name'],
                ];
                $free[$targetId]['minutes'] -= $item['minutes'];
                if (count($suggestions) >= 5) {
                    break 2;
                }
            }
        }

        return $suggestions;
    }

    /** @return User[] chi lavora in azienda (tutti gli utenti attivi) */
    private function staff(): array
    {
        return $this->users->findAll([], 'first_name');
    }

    private static function untilLabel(int $minute): string
    {
        return 'fino alle ' . DayPlanner::clock($minute);
    }
}
