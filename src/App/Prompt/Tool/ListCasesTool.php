<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CaseFileRepository;

final class ListCasesTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return CaseFileRepository::class;
    }

    protected function entityTag(): string
    {
        return 'case';
    }

    protected function baseUrl(): string
    {
        return '/pratiche';
    }

    protected function title(): string
    {
        return 'Pratiche';
    }

    protected function statLabel(): string
    {
        return 'Totale pratiche';
    }

    protected function createLabel(): ?string
    {
        return 'Nuova pratica';
    }

    protected function createPermission(): ?string
    {
        return 'cases.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'category', 'label' => 'Categoria'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'priority', 'label' => 'Priorita'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/pratiche/{id}', 'permission' => 'cases.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_cases';
    }

    public function description(): string
    {
        return "Mostra l'elenco delle pratiche con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Pratiche';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function requiredPermission(): ?string
    {
        return 'cases.view';
    }

    public function triggers(): array
    {
        return ['pratiche', 'lista pratiche', 'elenco pratiche', 'fascicoli'];
    }
}
