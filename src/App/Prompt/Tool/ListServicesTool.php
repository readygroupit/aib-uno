<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\ServiceRepository;

final class ListServicesTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return ServiceRepository::class;
    }

    protected function entityTag(): string
    {
        return 'service';
    }

    protected function baseUrl(): string
    {
        return '/servizi';
    }

    protected function title(): string
    {
        return 'Servizi';
    }

    protected function statLabel(): string
    {
        return 'Totale servizi';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo servizio';
    }

    protected function createPermission(): ?string
    {
        return 'services.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'category', 'label' => 'Categoria'],
            ['key' => 'price', 'label' => 'Prezzo'],
            ['key' => 'durationMinutes', 'label' => 'Durata (min)'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/servizi/{id}', 'permission' => 'services.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_services';
    }

    public function description(): string
    {
        return "Mostra l'elenco dei servizi offerti con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Servizi';
    }

    public function menuSection(): ?string
    {
        return 'vendite';
    }

    public function requiredPermission(): ?string
    {
        return 'services.view';
    }

    public function triggers(): array
    {
        return ['servizi', 'lista servizi', 'elenco servizi'];
    }
}
