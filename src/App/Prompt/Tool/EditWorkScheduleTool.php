<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\WorkScheduleRepository;

final class EditWorkScheduleTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return WorkScheduleRepository::class;
    }

    protected function packageName(): string
    {
        return 'planner';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_schedules';
    }

    protected function entityTag(): string
    {
        return 'work_schedule';
    }

    protected function baseUrl(): string
    {
        return '/orari';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo orario di lavoro' : 'Orario di lavoro';
    }

    protected function searchColumns(): array
    {
        return ['start_time'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'userId', 'label' => 'Persona'], ['key' => 'startTime', 'label' => 'Inizio']];
    }

    public function name(): string
    {
        return 'edit_work_schedule';
    }

    public function description(): string
    {
        return "Apre l'orario di lavoro di una persona.";
    }

    public function requiredPermission(): ?string
    {
        return 'planner.team';
    }
}
