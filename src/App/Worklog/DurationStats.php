<?php

declare(strict_types=1);

namespace App\Worklog;

use App\Core\Container;
use App\Model\Task;
use App\Package\PackageCatalogService;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\WorkOrderTypeStepRepository;
use App\Work\WorkOrderOverviewService;

/**
 * Quanto dura davvero un tipo di lavoro, dai tempi registrati.
 *
 * "Tipo di lavoro": per le fasi delle commesse e' la fase del modello (la
 * "Proposta grafica" del sito vetrina, chiunque la faccia e per qualunque
 * cliente); per le attivita' sciolte e' il titolo normalizzato (il "Report
 * mensile dei social" di ogni mese).
 *
 * Si usa la mediana, non la media: un caso eccezionale (il cliente che
 * cambia idea tre volte) non deve spostare la stima di tutti. Sotto i 3
 * casi non si dice niente: troppo pochi per essere un dato.
 */
final class DurationStats
{
    public const MIN_SAMPLES = 3;
    private const WINDOW_DAYS = 180;

    private TaskRepository $tasks;
    private UserRepository $users;
    private ?WorkOrderTypeStepRepository $steps = null;
    private ?WorkOrderTypeRepository $types = null;
    private ?WorkOrderRepository $orders = null;
    private ?array $groups = null;

    public function __construct(Container $container)
    {
        $this->tasks = $container->get(TaskRepository::class);
        $this->users = $container->get(UserRepository::class);
        if (in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            $this->steps = $container->get(WorkOrderTypeStepRepository::class);
            $this->types = $container->get(WorkOrderTypeRepository::class);
            $this->orders = $container->get(WorkOrderRepository::class);
        }
    }

    public static function typeKey(Task $task): string
    {
        if ($task->sourceStepId !== null) {
            return 'step:' . $task->sourceStepId;
        }
        $title = mb_strtolower((string) $task->title);
        $title = preg_replace('/[^\p{L}\s]/u', ' ', $title);

        return 'title:' . trim(preg_replace('/\s+/', ' ', (string) $title));
    }

    /** Minuti "veri" per un'attivita' di quel tipo, se ci sono abbastanza casi. */
    public function learnedMinutes(Task $task): ?int
    {
        $group = $this->groups()[self::typeKey($task)] ?? null;

        return $group !== null && count($group['samples']) >= self::MIN_SAMPLES ? $group['median'] : null;
    }

    /**
     * Una riga per tipo di lavoro con abbastanza casi: previsto, reale, per
     * persona, e la proposta di aggiornare il modello se lo scarto e' forte.
     */
    public function report(): array
    {
        $names = $this->userNames();
        $rows = [];
        foreach ($this->groups() as $key => $group) {
            $n = count($group['samples']);
            if ($n < self::MIN_SAMPLES) {
                continue;
            }
            $planned = $group['planned'];
            $delta = $planned > 0 ? (int) round(($group['median'] - $planned) / $planned * 100) : null;

            $people = [];
            foreach ($group['byUser'] as $userId => $values) {
                $people[] = ['name' => $names[$userId] ?? '?', 'median' => WorkOrderOverviewService::duration(self::median($values)), 'n' => count($values)];
            }

            $rows[] = [
                'key' => $key,
                'label' => $group['label'],
                'samples' => $n,
                'planned' => $planned > 0 ? WorkOrderOverviewService::duration($planned) : null,
                'real' => WorkOrderOverviewService::duration($group['median']),
                'range' => WorkOrderOverviewService::duration(min($group['samples'])) . ' - ' . WorkOrderOverviewService::duration(max($group['samples'])),
                'delta' => $delta,
                'deltaLabel' => $delta === null ? null : ($delta === 0 ? 'come previsto' : ($delta > 0 ? "+{$delta}%" : "{$delta}%")),
                'tone' => $delta === null ? 'neutral' : (abs($delta) >= 25 ? ($delta > 0 ? 'danger' : 'info') : 'success'),
                'people' => $people,
                // Lo scarto e' forte: il modello va aggiornato con il tempo vero.
                'applyAction' => $group['stepId'] !== null && $delta !== null && abs($delta) >= 20 && abs($group['median'] - $planned) >= 30
                    ? '/registro/modello?fase=' . $group['stepId'] . '&minuti=' . (int) (round($group['median'] / 15) * 15)
                    : null,
                'applyLabel' => 'Usa ' . WorkOrderOverviewService::duration((int) (round($group['median'] / 15) * 15)) . ' nel modello',
            ];
        }
        usort($rows, static fn (array $a, array $b) => [abs($b['delta'] ?? 0), $b['samples']] <=> [abs($a['delta'] ?? 0), $a['samples']]);

        return $rows;
    }

    /**
     * Da guardare, senza giudicare: lavori recenti che sono durati molto
     * piu' del solito, e persone che sullo stesso tipo di lavoro ci mettono
     * stabilmente piu' degli altri. Sono domande da farsi (imprevisti,
     * richieste del cliente, serve una mano?), non sentenze.
     */
    public function anomalies(int $recentDays = 21): array
    {
        $names = $this->userNames();
        $since = date('Y-m-d', strtotime("-{$recentDays} days"));
        $result = [];

        foreach ($this->groups() as $group) {
            if (count($group['samples']) < self::MIN_SAMPLES) {
                continue;
            }
            foreach ($group['items'] as $item) {
                if ($item['completed'] < $since) {
                    continue;
                }
                $others = array_values(array_filter($group['items'], static fn (array $i) => $i['taskId'] !== $item['taskId']));
                if (count($others) < 2) {
                    continue;
                }
                $usual = self::median(array_column($others, 'minutes'));
                if ($usual > 0 && $item['minutes'] >= $usual * 1.5 && $item['minutes'] - $usual >= 60) {
                    $result[] = [
                        'tone' => 'warning',
                        'text' => "\u{ab}{$item['title']}\u{bb}" . ($item['context'] ? " ({$item['context']})" : '') . ': '
                            . WorkOrderOverviewService::duration($item['minutes']) . ' contro le solite ' . WorkOrderOverviewService::duration($usual)
                            . ($item['userId'] !== null && isset($names[$item['userId']]) ? ", fatta da {$names[$item['userId']]}" : '') . '. Da capire: imprevisti, richieste in piu\' del cliente?',
                        'href' => '/attivita/' . $item['taskId'],
                    ];
                }
            }

            // Persona contro gli altri, sullo stesso lavoro (almeno 2 casi a testa).
            foreach ($group['byUser'] as $userId => $values) {
                $othersValues = [];
                foreach ($group['byUser'] as $otherId => $otherValues) {
                    if ($otherId !== $userId) {
                        array_push($othersValues, ...$otherValues);
                    }
                }
                if (count($values) < 2 || count($othersValues) < 2) {
                    continue;
                }
                $mine = self::median($values);
                $theirs = self::median($othersValues);
                if ($theirs > 0 && $mine >= $theirs * 1.4 && $mine - $theirs >= 30) {
                    $result[] = [
                        'tone' => 'info',
                        'text' => "Per \u{ab}{$group['label']}\u{bb} " . ($names[$userId] ?? '?') . ' impiega di solito ' . WorkOrderOverviewService::duration($mine)
                            . ', gli altri ' . WorkOrderOverviewService::duration($theirs) . '. Vale una chiacchierata: metodo diverso, lavori piu\' complessi, serve aiuto?',
                        'href' => null,
                    ];
                }
            }
        }

        return $result;
    }

    /** @return array<string, array> tipo => casi, mediana, previsto, per persona */
    private function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $stepLabels = $this->stepLabels();
        $orderTitles = $this->orders !== null ? [] : null;
        $this->groups = [];
        foreach ($this->tasks->findDoneWithTimeSince(date('Y-m-d', strtotime('-' . self::WINDOW_DAYS . ' days'))) as $task) {
            $key = self::typeKey($task);
            $stepId = $task->sourceStepId;
            $this->groups[$key] ??= [
                'label' => $stepId !== null ? ($stepLabels[$stepId]['label'] ?? (string) $task->title) : (string) $task->title,
                'stepId' => $stepId,
                'plannedValues' => [],
                'samples' => [],
                'byUser' => [],
                'items' => [],
                'stepEstimate' => $stepId !== null ? ($stepLabels[$stepId]['estimate'] ?? null) : null,
            ];
            $minutes = (int) $task->actualMinutes;
            $this->groups[$key]['samples'][] = $minutes;
            if ($task->estimatedMinutes) {
                $this->groups[$key]['plannedValues'][] = (int) $task->estimatedMinutes;
            }
            if ($task->assignedUserId !== null) {
                $this->groups[$key]['byUser'][(int) $task->assignedUserId][] = $minutes;
            }
            $context = null;
            if ($task->entityType === 'work_orders' && $task->entityId !== null && $this->orders !== null) {
                $orderTitles[(int) $task->entityId] ??= (string) ($this->orders->find((int) $task->entityId)?->title ?? '');
                $context = $orderTitles[(int) $task->entityId] ?: null;
            }
            $this->groups[$key]['items'][] = [
                'taskId' => (int) $task->id,
                'title' => (string) $task->title,
                'context' => $context,
                'minutes' => $minutes,
                'userId' => $task->assignedUserId,
                'completed' => substr((string) $task->completedAt, 0, 10),
            ];
        }

        foreach ($this->groups as &$group) {
            $group['median'] = self::median($group['samples']);
            // Il previsto: quello del modello (se e' una fase), altrimenti la stima tipica.
            $group['planned'] = (int) ($group['stepEstimate'] ?? ($group['plannedValues'] !== [] ? self::median($group['plannedValues']) : 0));
        }
        unset($group);

        return $this->groups;
    }

    /** @return array<int, array{label:string, estimate:?int}> */
    private function stepLabels(): array
    {
        if ($this->steps === null) {
            return [];
        }
        $typeNames = [];
        foreach ($this->types->findAll() as $type) {
            $typeNames[(int) $type->id] = (string) $type->name;
        }
        $labels = [];
        foreach ($this->steps->findAll() as $step) {
            $labels[(int) $step->id] = [
                'label' => $step->title . (isset($typeNames[(int) $step->workOrderTypeId]) ? ' · ' . $typeNames[(int) $step->workOrderTypeId] : ''),
                'estimate' => $step->estimatedMinutes,
            ];
        }

        return $labels;
    }

    /** @return array<int,string> */
    private function userNames(): array
    {
        $names = [];
        foreach ($this->users->findAll() as $user) {
            $names[(int) $user->id] = trim((string) $user->firstName) ?: (string) $user->username;
        }

        return $names;
    }

    /** @param int[] $values */
    public static function median(array $values): int
    {
        if ($values === []) {
            return 0;
        }
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 === 1 ? (int) $values[$mid] : (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }
}
