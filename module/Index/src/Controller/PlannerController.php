<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\Task;
use App\Planner\DayPlanner;
use App\Prompt\Tool\ShowDayPlanTool;
use App\Prompt\Tool\ShowTeamPlanTool;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\Work\TaskService;

/**
 * Organizer: la giornata (/oggi), il team (/team) e le due azioni che lo
 * rendono utile davvero: rimandare quello che non ci sta e passare un
 * lavoro a chi ha tempo.
 */
final class PlannerController extends AuthController
{
    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'team', 'reassign' => 'planner.team',
            default => 'planner.view',
        };
    }

    /** /oggi?giorno=AAAA-MM-GG&persona=ID, o ?settimana=AAAA-MM-GG per la settimana intera */
    public function todayAction(): ?string
    {
        $components = $this->container->get(ShowDayPlanTool::class)->execute([
            'day' => (string) $this->request->get('giorno', ''),
            'week' => (string) $this->request->get('settimana', ''),
            'person' => (string) $this->request->get('persona', ''),
        ]);

        return $this->renderPage($components, ['title' => 'La mia giornata']);
    }

    public function teamAction(): ?string
    {
        $components = $this->container->get(ShowTeamPlanTool::class)->execute(['week' => (string) $this->request->get('settimana', '')]);

        return $this->renderPage($components, ['title' => 'Il team']);
    }

    /**
     * Sposta al prossimo giorno lavorativo quello che non ci sta (o una
     * sola attivita' con ?task=ID). La propria giornata, o quella di un
     * collega con il permesso di organizzare il team.
     */
    public function postponeAction(): void
    {
        $auth = $this->container->get(AuthService::class);
        $userId = (int) ($this->request->get('persona') ?: $auth->currentUserId());
        if ($userId !== (int) $auth->currentUserId() && !$auth->hasPermission('planner.team')) {
            $this->json(['ok' => false, 'message' => 'Puoi spostare solo le tue attivita\'.'], 403);

            return;
        }
        $date = (string) $this->request->get('giorno', date('Y-m-d'));
        $planner = $this->container->get(DayPlanner::class);
        $plan = $planner->plan($userId, $date);
        $only = $this->request->get('task');

        $tasks = $this->container->get(TaskRepository::class);
        $moved = 0;
        foreach ($plan['overflow'] as $item) {
            if (!$item['movable'] || ($only !== null && (string) $item['id'] !== (string) $only)) {
                continue;
            }
            $tasks->update((int) $item['id'], ['planned_date' => $plan['nextWorkday']]);
            $task = $tasks->find((int) $item['id']);
            if ($task instanceof Task) {
                $this->container->get(TaskService::class)->afterChange($task);
            }
            $moved++;
        }

        $this->json([
            'ok' => true,
            'message' => $moved > 0 ? F::plural($moved, "attivita' spostata", "attivita' spostate") . ' a ' . $plan['nextWorkdayLabel'] . '.' : 'Niente da spostare.',
        ]);
    }

    /** Passa un'attivita' a un collega, per oggi. */
    public function reassignAction(): void
    {
        $tasks = $this->container->get(TaskRepository::class);
        $task = $tasks->find((int) $this->request->get('task', 0));
        $user = $this->container->get(UserRepository::class)->find((int) $this->request->get('persona', 0));
        if (!$task instanceof Task || $user === null) {
            $this->json(['ok' => false, 'message' => 'Attivita\' o persona non trovata.'], 404);

            return;
        }

        $data = ['assigned_user_id' => $user->id];
        if ($task->plannedDate === null || $task->plannedDate < date('Y-m-d')) {
            $data['planned_date'] = date('Y-m-d');
        }
        $tasks->update((int) $task->id, $data);

        $this->json(['ok' => true, 'message' => "\u{ab}{$task->title}\u{bb} passata a " . (trim((string) $user->firstName) ?: $user->username) . '.']);
    }
}
