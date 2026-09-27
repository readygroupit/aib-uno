<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\AppointmentRepository;

final class EditAppointmentTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return AppointmentRepository::class;
    }

    protected function packageName(): string
    {
        return 'appointments';
    }

    protected function entityTag(): string
    {
        return 'appointment';
    }

    protected function baseUrl(): string
    {
        return '/appuntamenti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo appuntamento' : 'Modifica appuntamento';
    }

    protected function searchColumns(): array
    {
        return ['title', 'location', 'notes'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'startAt', 'label' => 'Inizio'],
            ['key' => 'location', 'label' => 'Luogo'],
        ];
    }

    public function name(): string
    {
        return 'edit_appointment';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un appuntamento, cercato per id o per titolo/luogo/note. "
            . "Usalo quando l'utente chiede di aprire o modificare un appuntamento specifico.";
    }

    public function requiredPermission(): ?string
    {
        return 'appointments.edit';
    }
}
