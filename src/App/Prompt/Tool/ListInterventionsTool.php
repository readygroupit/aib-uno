<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\InterventionRepository;

final class ListInterventionsTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return InterventionRepository::class;
    }

    protected function entityTag(): string
    {
        return 'intervention';
    }

    protected function baseUrl(): string
    {
        return '/interventi';
    }

    protected function title(): string
    {
        return 'Interventi';
    }

    protected function statLabel(): string
    {
        return 'Totale interventi';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo intervento';
    }

    protected function createPermission(): ?string
    {
        return 'interventions.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'scheduledAt', 'label' => 'Data'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'operationType', 'label' => 'Tipo'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/interventi/{id}', 'permission' => 'interventions.edit'],
        ];
    }

    protected function orderBy(): string
    {
        return 'scheduled_at DESC';
    }

    public function name(): string
    {
        return 'list_interventions';
    }

    public function description(): string
    {
        return "Mostra l'elenco degli interventi tecnici con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Interventi';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function requiredPermission(): ?string
    {
        return 'interventions.view';
    }

    public function triggers(): array
    {
        return ['interventi', 'lista interventi', 'elenco interventi'];
    }
}
