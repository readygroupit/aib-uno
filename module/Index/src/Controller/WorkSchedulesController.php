<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\WorkSchedule;
use App\Prompt\Tool\EditWorkScheduleTool;
use App\Prompt\Tool\ListWorkSchedulesTool;
use App\Repository\UserRepository;
use App\Repository\WorkScheduleRepository;

/**
 * Orari di lavoro: li imposta chi organizza il team (planner.team). Un
 * orario per persona (l'organizer usa quello): un nuovo orario si puo'
 * dare a piu' persone insieme, se lavorano con gli stessi orari.
 */
final class WorkSchedulesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkScheduleRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkScheduleTool::class;
    }

    protected function listToolClass(): string
    {
        return ListWorkSchedulesTool::class;
    }

    protected function packageName(): string
    {
        return 'planner';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_schedules';
    }

    protected function listPageTitle(): string
    {
        return 'Orari di lavoro';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo orario';
    }

    protected function editPageTitle(): string
    {
        return 'Orario di lavoro';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return 'planner.team';
    }

    protected function defaultsOnCreate(): array
    {
        return ['start_time' => '09:00', 'end_time' => '18:00', 'break_start' => '13:00', 'break_end' => '14:00', 'workdays' => '1,2,3,4,5', 'focus_percent' => '85'];
    }

    /** Un orario per ogni persona scelta; con una sola persona, la sua scheda come sempre. */
    public function newAction(): ?string
    {
        if ($this->request->getMethod() !== 'POST') {
            return parent::newAction();
        }

        /** @var EditWorkScheduleTool $tool */
        $tool = $this->container->get(EditWorkScheduleTool::class);
        [$data, $fieldErrors] = $this->collectAndValidate();
        $userIds = $this->selectedUserIds();

        if ($fieldErrors !== []) {
            $tool->selectUsers(implode(',', $userIds));

            return $this->renderPage([$tool->buildForm(WorkSchedule::fromArray($data), null, null, $fieldErrors)], ['title' => $this->newPageTitle()]);
        }

        foreach ($this->defaultsOnCreate() as $key => $default) {
            $data[$key] ??= $default;
        }
        $repo = $this->repository();
        $ids = [];
        foreach ($userIds as $userId) {
            $ids[] = $repo->insert(['user_id' => $userId] + $data);
        }

        if (count($ids) === 1) {
            return $this->renderPage($this->componentsAfterCreate($ids[0]), ['title' => $this->newPageTitle()]);
        }

        $notice = ['type' => 'notice', 'tone' => 'success', 'text' => 'Orario salvato per ' . count($ids) . " persone: l'organizer lo usa da subito."];
        $list = $this->container->get(ListWorkSchedulesTool::class)->execute(['page' => 1]);

        return $this->renderPage([$notice, ...$list], ['title' => $this->listPageTitle()]);
    }

    /** Nel form nuovo le persone sono piu' d'una ("2,3"): per i controlli vale la prima, l'elenco lo legge selectedUserIds(). */
    protected function requestValue(string $key): mixed
    {
        $raw = parent::requestValue($key);
        if ($key !== 'user_id' || $raw === null) {
            return $raw;
        }
        $ids = $this->selectedUserIds();

        return $ids === [] ? '' : (string) $ids[0];
    }

    /** @return list<int> */
    private function selectedUserIds(): array
    {
        $raw = (string) ($this->request->get('user_id', '') ?? '');
        $ids = array_map('intval', array_filter(array_map('trim', explode(',', $raw)), 'ctype_digit'));

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

    protected function crossFieldErrors(array $data): array
    {
        $start = $data['start_time'] ?? null;
        $end = $data['end_time'] ?? null;
        if ($start !== null && $end !== null && $end <= $start) {
            return ['end_time' => "La fine dev'essere dopo l'inizio."];
        }

        $breakStart = $data['break_start'] ?? null;
        $breakEnd = $data['break_end'] ?? null;
        if (array_key_exists('break_start', $data) && ($breakStart === null) !== ($breakEnd === null)) {
            return $breakStart === null
                ? ['break_start' => 'Manca l\'inizio della pausa (o togli anche la fine).']
                : ['break_end' => 'Manca la fine della pausa (o togli anche l\'inizio).'];
        }
        if ($breakStart !== null && $breakEnd !== null) {
            if ($breakEnd <= $breakStart) {
                return ['break_end' => "La pausa deve finire dopo l'inizio."];
            }
            if ($start !== null && $end !== null && ($breakStart < $start || $breakEnd > $end)) {
                return ['break_start' => "La pausa dev'essere dentro l'orario di lavoro ({$start}-{$end})."];
            }
        }

        if (array_key_exists('workdays', $data) && $data['workdays'] === null) {
            return ['workdays' => 'Scegli almeno un giorno.'];
        }
        if (isset($data['focus_percent']) && $data['focus_percent'] !== null && ((int) $data['focus_percent'] < 10 || (int) $data['focus_percent'] > 100)) {
            return ['focus_percent' => 'Tra 10 e 100.'];
        }

        return $this->alreadyScheduled();
    }

    /**
     * Una persona ha un orario solo: se ce l'ha gia', si cambia quello
     * (prima ne salvava un secondo uguale senza dire niente).
     */
    private function alreadyScheduled(): array
    {
        $currentId = (int) $this->param('id', 0);
        $repo = $this->repository();
        $users = $this->container->get(UserRepository::class);
        $names = [];
        foreach ($this->selectedUserIds() as $userId) {
            foreach ($repo->findAll(['user_id' => $userId]) as $schedule) {
                if ((int) $schedule->id !== $currentId) {
                    $user = $users->find($userId);
                    $names[] = $user !== null ? (trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username) : "#{$userId}";
                    break;
                }
            }
        }
        if ($names === []) {
            return [];
        }

        return ['user_id' => (count($names) === 1 ? "{$names[0]} ha gia' un orario" : "Hanno gia' un orario: " . implode(', ', $names))
            . ": cambialo dall'elenco degli orari invece di crearne un altro."];
    }

    protected function createdMessage(): string
    {
        return "Orario salvato: l'organizer lo usa da subito.";
    }
}
