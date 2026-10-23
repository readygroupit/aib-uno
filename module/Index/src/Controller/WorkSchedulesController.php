<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditWorkScheduleTool;
use App\Prompt\Tool\ListWorkSchedulesTool;
use App\Repository\WorkScheduleRepository;

/** Orari di lavoro: li imposta chi organizza il team (planner.team). */
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

    protected function crossFieldErrors(array $data): array
    {
        if (!empty($data['start_time']) && !empty($data['end_time']) && $data['end_time'] <= $data['start_time']) {
            return ['end_time' => "La fine dev'essere dopo l'inizio."];
        }
        if (isset($data['focus_percent']) && $data['focus_percent'] !== null && ((int) $data['focus_percent'] < 10 || (int) $data['focus_percent'] > 100)) {
            return ['focus_percent' => 'Tra 10 e 100.'];
        }

        return [];
    }

    protected function createdMessage(): string
    {
        return "Orario salvato: l'organizer lo usa da subito.";
    }
}
