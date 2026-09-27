<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\CaseFileRepository;

final class EditCaseTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return CaseFileRepository::class;
    }

    protected function packageName(): string
    {
        return 'cases';
    }

    protected function entityTag(): string
    {
        return 'case';
    }

    protected function baseUrl(): string
    {
        return '/pratiche';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova pratica' : 'Modifica pratica';
    }

    protected function searchColumns(): array
    {
        return ['title', 'category', 'notes'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'category', 'label' => 'Categoria'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    public function name(): string
    {
        return 'edit_case';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di una pratica, cercata per id o per titolo/categoria/note. "
            . "Usalo quando l'utente chiede di aprire o modificare una pratica specifica.";
    }

    public function requiredPermission(): ?string
    {
        return 'cases.edit';
    }
}
