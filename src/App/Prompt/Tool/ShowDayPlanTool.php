<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Planner\PlannerOverviewService;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\View\Component\DayPlanComponent;
use App\View\Component\WeekPlanComponent;
use App\Work\WorkOrderOverviewService;

/**
 * "Cosa devo fare oggi": la giornata messa in ordine dal sistema. Di chi
 * chiede, oppure (con il permesso di organizzare il team) di un collega.
 */
final class ShowDayPlanTool implements PromptToolInterface, PromptConversationalInterface
{
    private PlannerOverviewService $overview;
    private UserRepository $users;
    private AuthService $auth;
    private DayPlanComponent $component;
    private WeekPlanComponent $weekComponent;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(PlannerOverviewService::class);
        $this->users = $container->get(UserRepository::class);
        $this->auth = $container->get(AuthService::class);
        $this->component = $container->get(DayPlanComponent::class);
        $this->weekComponent = $container->get(WeekPlanComponent::class);
    }

    public function name(): string
    {
        return 'show_my_day';
    }

    public function description(): string
    {
        return "L'organizer: la giornata messa in ordine (appuntamenti, cose da fare con l'orario, solleciti, cosa non ci sta, "
            . "cosa aspetta il cliente). 'day' = AAAA-MM-GG per un altro giorno (es. domani); 'person' = nome di un collega; "
            . "'week' = una data della settimana per la scaletta di tutta la settimana di quella persona. "
            . "Usalo per 'cosa devo fare oggi', 'la mia giornata', 'cosa ho domani', 'cosa deve fare Lena oggi', "
            . "'la scaletta di Sara la prossima settimana', 'come e' messa Sara questa settimana'.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'day' => ['type' => 'string', 'description' => 'Giorno AAAA-MM-GG (default oggi)'],
                'week' => ['type' => 'string', 'description' => 'Una data AAAA-MM-GG della settimana da mostrare intera (al posto di day)'],
                'person' => ['type' => 'string', 'description' => 'Nome del collega (default chi chiede)'],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'La mia giornata';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return 'planner.view';
    }

    public function triggers(): array
    {
        return ['oggi', 'la mia giornata', 'cosa devo fare', 'cosa devo fare oggi', 'organizer', 'il mio programma', 'programma di oggi'];
    }

    public function execute(array $input): array
    {
        [$userId, $date] = $this->resolve($input);
        $me = $this->auth->currentUserId();
        if (self::weekOf($input) !== null) {
            return [$this->weekComponent($userId, (string) self::weekOf($input))];
        }
        $data = $this->overview->myDay($userId, $date);
        $canTeam = $this->auth->hasPermission('planner.team');

        $query = array_filter(['persona' => $userId !== $me ? $userId : null, 'giorno' => $date !== date('Y-m-d') ? $date : null]);

        return [$this->component->toData($data + [
            'url' => '/oggi' . ($query !== [] ? '?' . http_build_query($query) : ''),
            'personId' => $userId,
            'isMe' => $userId === $me,
            'canTeam' => $canTeam,
            'nav' => [
                'prev' => '/oggi?' . http_build_query(array_filter(['persona' => $userId !== $me ? $userId : null, 'giorno' => date('Y-m-d', strtotime("{$date} -1 day"))])),
                'today' => '/oggi' . ($userId !== $me ? '?persona=' . $userId : ''),
                'next' => '/oggi?' . http_build_query(array_filter(['persona' => $userId !== $me ? $userId : null, 'giorno' => $data['plan']['nextWorkday']])),
            ],
            'postponeAction' => '/oggi/rimanda?' . http_build_query(['persona' => $userId, 'giorno' => $date]),
            'people' => $canTeam ? array_map(static fn ($u) => ['id' => (int) $u->id, 'name' => trim((string) $u->firstName) ?: (string) $u->username], $this->users->findAll([], 'first_name')) : [],
        ])];
    }

    /** Lunedi' della settimana chiesta, o null se si chiede un giorno solo. */
    private static function weekOf(array $input): ?string
    {
        $week = (string) ($input['week'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $week) !== 1) {
            return null;
        }

        return date('Y-m-d', strtotime('monday this week', strtotime($week)));
    }

    private function weekComponent(int $userId, string $monday): array
    {
        $me = $this->auth->currentUserId();
        $person = $userId !== $me ? ['persona' => $userId] : [];
        $data = $this->overview->personWeek($userId, $monday);
        $sunday = date('Y-m-d', strtotime("{$monday} +6 days"));

        return $this->weekComponent->toData([
            'url' => '/oggi?' . http_build_query($person + ['settimana' => $monday]),
            'title' => $userId === $me ? 'La mia settimana' : 'La settimana di ' . $data['name'],
            'weekLabel' => 'dal ' . (int) date('j', strtotime($monday)) . ' ' . \App\Support\ItalianFormat::monthName((int) date('n', strtotime($monday)))
                . ' al ' . (int) date('j', strtotime($sunday)) . ' ' . \App\Support\ItalianFormat::monthName((int) date('n', strtotime($sunday))),
            'summary' => \App\Support\ItalianFormat::plural($data['appointments'], 'appuntamento', 'appuntamenti') . ' · ' . $data['plannedLabel'] . ' pianificate',
            'days' => $data['days'],
            'nav' => [
                'prev' => '/oggi?' . http_build_query($person + ['settimana' => date('Y-m-d', strtotime("{$monday} -7 days"))]),
                'next' => '/oggi?' . http_build_query($person + ['settimana' => date('Y-m-d', strtotime("{$monday} +7 days"))]),
                'current' => '/oggi?' . http_build_query($person + ['settimana' => date('Y-m-d', strtotime('monday this week'))]),
                'isCurrent' => $monday === date('Y-m-d', strtotime('monday this week')),
                'jump' => '/oggi?' . http_build_query($person + ['settimana' => '']),
                'today' => '/oggi' . ($person !== [] ? '?persona=' . $userId : ''),
            ],
        ]);
    }

    public function replySummary(array $input): ?string
    {
        [$userId, $date] = $this->resolve($input);
        $monday = self::weekOf($input);
        if ($monday !== null) {
            $data = $this->overview->personWeek($userId, $monday);
            $who = $userId === $this->auth->currentUserId() ? 'Hai' : $data['name'] . ' ha';

            return "{$who} " . \App\Support\ItalianFormat::plural($data['appointments'], 'appuntamento', 'appuntamenti')
                . ' e ' . $data['plannedLabel'] . ' di lavoro pianificato nella settimana del ' . (int) date('j', strtotime($monday)) . '.';
        }
        $data = $this->overview->myDay($userId, $date);
        $first = null;
        foreach ($data['plan']['timeline'] as $row) {
            if (in_array($row['type'], ['task', 'reminder', 'appointment'], true)) {
                $first = $row;
                break;
            }
        }

        return $data['greeting']['text'] . ($first !== null ? ' Si parte alle ' . substr($first['time'], 0, 5) . " con \u{ab}{$first['title']}\u{bb}." : '');
    }

    public function followUpSuggestions(): array
    {
        return ['Cosa ho domani?', 'Come e\' messo il team?', 'Calendario'];
    }

    /** @return array{0:int, 1:string} persona e giorno, con i controlli */
    private function resolve(array $input): array
    {
        $me = (int) $this->auth->currentUserId();
        $date = isset($input['day']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['day']) === 1 ? (string) $input['day'] : date('Y-m-d');
        $userId = $me;

        $person = trim((string) ($input['person'] ?? ''));
        if ($person !== '' && $this->auth->hasPermission('planner.team')) {
            if (ctype_digit($person)) {
                $userId = (int) $person;
            } else {
                foreach ($this->users->findAll() as $user) {
                    if (stripos(trim("{$user->firstName} {$user->lastName}"), $person) !== false || strcasecmp((string) $user->username, $person) === 0) {
                        $userId = (int) $user->id;
                        break;
                    }
                }
            }
        }

        return [$userId, $date];
    }
}
