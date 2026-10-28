<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\TaskRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\StatBoxComponent;
use App\View\Component\TaskBoardComponent;
use App\Work\WorkOrderOverviewService;

/**
 * Le attivita' per giorno, di chi e' collegato (o di tutti): in ritardo,
 * oggi, prossimi giorni. Sono sia le fasi delle commesse sia le attivita'
 * sciolte; si spuntano con un clic.
 */
final class ListTasksTool implements PromptToolInterface, PromptConversationalInterface
{
    private WorkOrderOverviewService $overview;
    private TaskRepository $tasks;
    private StatBoxComponent $statBox;
    private TaskBoardComponent $board;
    private AuthService $auth;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->overview = $container->get(WorkOrderOverviewService::class);
        $this->tasks = $container->get(TaskRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->board = $container->get(TaskBoardComponent::class);
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return "Le attivita' da fare (anche le fasi delle commesse) per giorno: in ritardo, oggi, prossimi giorni. "
            . "Di default quelle di chi chiede; scope 'tutte' per tutto il team. Usalo per 'attivita', "
            . "'le mie attivita', 'attivita di tutti' (per 'cosa devo fare oggi' c'e' l'organizer).";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'scope' => ['type' => 'string', 'enum' => ['mie', 'tutte'], 'description' => "Le proprie (default) o quelle di tutti"],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'Attivita';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        $t = $this->overview->taskBoard($this->auth->currentUserId())['totals'];

        return $t['today'] + $t['late'] > 0 ? ($t['today'] + $t['late']) . ' oggi' : null;
    }

    public function requiredPermission(): ?string
    {
        return 'tasks.view';
    }

    public function triggers(): array
    {
        return ['attivita', 'le mie attivita', 'promemoria', 'lista attivita'];
    }

    /**
     * Gli appuntamenti di oggi e dei prossimi 7 giorni (di chi guarda, o di
     * tutti): contano anche loro, anche di sabato o domenica - prima la
     * pagina diceva "settimana libera" con un appuntamento domani.
     *
     * @return array{today: int, week: int, items: list<array>}
     */
    private function upcomingAppointments(?int $userId): array
    {
        if (!in_array('appointments', \App\Package\PackageCatalogService::installedNames(), true)) {
            return ['today' => 0, 'week' => 0, 'items' => []];
        }
        $today = date('Y-m-d');
        $found = $this->container->get(\App\Repository\AppointmentRepository::class)
            ->findBetween(date('Y-m-d H:i:s'), date('Y-m-d', strtotime($today . ' +8 days')) . ' 00:00:00');
        $mine = array_values(array_filter($found, static fn ($a) => $a->isActive() && ($userId === null || in_array($userId, $a->peopleIds(), true))));
        $onToday = count(array_filter($mine, static fn ($a) => substr((string) $a->startAt, 0, 10) <= $today));

        return [
            'today' => $onToday,
            'week' => count($mine) - $onToday,
            'items' => $this->container->get(\App\Calendar\CalendarOverviewService::class)->shortList($mine),
        ];
    }

    private static function countLabel(int $tasks, int $appointments): string
    {
        $parts = array_filter([
            $tasks > 0 ? F::plural($tasks, "attivita'", "attivita'") : null,
            $appointments > 0 ? F::plural($appointments, 'appuntamento', 'appuntamenti') : null,
        ]);

        return implode(' e ', $parts);
    }

    public function execute(array $input): array
    {
        $all = ($input['scope'] ?? 'mie') === 'tutte';
        $board = $this->overview->taskBoard($all ? null : $this->auth->currentUserId());
        $t = $board['totals'];
        $appointments = $this->upcomingAppointments($all ? null : $this->auth->currentUserId());
        $todayCount = $t['today'] + $appointments['today'];
        $weekCount = $t['week'] + $appointments['week'];

        return [$this->board->toData([
            'title' => $all ? "Attivita' di tutti" : "Le mie attivita'",
            'url' => $all ? '/attivita?tutte=1' : '/attivita',
            'scope' => $all ? 'tutte' : 'mie',
            'createHref' => '/attivita/nuovo' . ($all ? '' : '?assigned_user_id=' . $this->auth->currentUserId()),
            'stats' => [
                $this->statBox->toData([
                    'label' => 'Oggi',
                    'value' => (string) $todayCount,
                    'comparison' => $todayCount === 0 ? 'niente di previsto'
                        : self::countLabel($t['today'], $appointments['today']) . ($t['todayMinutes'] > 0 ? ', circa ' . WorkOrderOverviewService::duration($t['todayMinutes']) . ' di lavoro' : ''),
                    'feature' => true,
                    'badge' => ucfirst(F::longDate(date('Y-m-d'))),
                ]),
                $this->statBox->toData([
                    'label' => 'In ritardo',
                    'value' => (string) $t['late'],
                    'comparison' => $t['late'] > 0 ? 'da recuperare o ripianificare' : 'niente indietro',
                    'dotColor' => $t['late'] > 0 ? 'danger' : 'moss',
                ]),
                $this->statBox->toData([
                    'label' => 'Prossimi 7 giorni',
                    'value' => (string) $weekCount,
                    'comparison' => $weekCount === 0 ? 'settimana libera'
                        : self::countLabel($t['week'], $appointments['week']) . ($t['weekMinutes'] > 0 ? ', circa ' . WorkOrderOverviewService::duration($t['weekMinutes']) : ''),
                    'dotColor' => 'teal',
                ]),
                $this->statBox->toData([
                    'label' => 'Aspettano il cliente',
                    'value' => (string) $t['waitingClient'],
                    'comparison' => 'non dipendono da noi',
                    'dotColor' => 'brass',
                ]),
            ],
            'groups' => $board['groups'],
            'appointments' => $appointments['items'],
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $all = ($input['scope'] ?? 'mie') === 'tutte';
        $t = $this->overview->taskBoard($all ? null : $this->auth->currentUserId())['totals'];
        $who = $all ? 'Il team ha' : 'Hai';
        $sentence = $t['today'] > 0
            ? "{$who} " . F::plural($t['today'], "attivita'", "attivita'") . ' per oggi' . ($t['todayMinutes'] > 0 ? ' (circa ' . WorkOrderOverviewService::duration($t['todayMinutes']) . ')' : '')
            : "{$who} niente di previsto per oggi";
        if ($t['late'] > 0) {
            $sentence .= $t['late'] === 1 ? ', piu\' una in ritardo' : ", piu' {$t['late']} in ritardo";
        }

        return $sentence . '. ' . ($t['week'] > 0 ? 'Nei prossimi 7 giorni: ' . $t['week'] . '.' : '');
    }

    public function followUpSuggestions(): array
    {
        return ["Attivita' di tutti", 'Commesse', 'Cosa aspettiamo dai clienti?'];
    }
}
