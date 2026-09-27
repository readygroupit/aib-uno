<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\AppointmentRepository;

final class ListAppointmentsTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return AppointmentRepository::class;
    }

    protected function entityTag(): string
    {
        return 'appointment';
    }

    protected function baseUrl(): string
    {
        return '/appuntamenti';
    }

    protected function title(): string
    {
        return 'Appuntamenti';
    }

    protected function statLabel(): string
    {
        return 'Totale appuntamenti';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo appuntamento';
    }

    protected function createPermission(): ?string
    {
        return 'appointments.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'startAt', 'label' => 'Inizio'],
            ['key' => 'location', 'label' => 'Luogo'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/appuntamenti/{id}', 'permission' => 'appointments.edit'],
        ];
    }

    protected function orderBy(): string
    {
        return 'start_at ASC';
    }

    public function name(): string
    {
        return 'list_appointments';
    }

    public function description(): string
    {
        return "Mostra l'elenco degli appuntamenti con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Appuntamenti';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function requiredPermission(): ?string
    {
        return 'appointments.view';
    }

    public function triggers(): array
    {
        return ['appuntamenti', 'lista appuntamenti', 'calendario', 'agenda'];
    }
}
