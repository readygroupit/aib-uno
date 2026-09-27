<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\TaskRepository;

final class ListTasksTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return TaskRepository::class;
    }

    protected function entityTag(): string
    {
        return 'task';
    }

    protected function baseUrl(): string
    {
        return '/attivita';
    }

    protected function title(): string
    {
        return 'Attivita';
    }

    protected function statLabel(): string
    {
        return 'Totale attivita';
    }

    protected function createLabel(): ?string
    {
        return 'Nuova attivita';
    }

    protected function createPermission(): ?string
    {
        return 'tasks.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'dueAt', 'label' => 'Scadenza'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'assignedUserId', 'label' => 'Operatore'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/attivita/{id}', 'permission' => 'tasks.edit'],
        ];
    }

    protected function orderBy(): string
    {
        return 'due_at ASC';
    }

    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return "Mostra l'elenco delle attivita/promemoria con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Attivita';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function requiredPermission(): ?string
    {
        return 'tasks.view';
    }

    public function triggers(): array
    {
        return ['attivita', 'lista attivita', 'promemoria', 'lista promemoria'];
    }
}
