<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\ServiceRepository;

final class EditServiceTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return ServiceRepository::class;
    }

    protected function packageName(): string
    {
        return 'services';
    }

    protected function entityTag(): string
    {
        return 'service';
    }

    protected function baseUrl(): string
    {
        return '/servizi';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo servizio' : 'Modifica servizio';
    }

    protected function searchColumns(): array
    {
        return ['name', 'category', 'description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'category', 'label' => 'Categoria'],
            ['key' => 'price', 'label' => 'Prezzo'],
        ];
    }

    public function name(): string
    {
        return 'edit_service';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un servizio, cercato per id o per nome/categoria/descrizione. "
            . "Usalo quando l'utente chiede di aprire o modificare uno specifico servizio.";
    }

    public function requiredPermission(): ?string
    {
        return 'services.edit';
    }
}
