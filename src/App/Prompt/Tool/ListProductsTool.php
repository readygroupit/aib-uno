<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\ProductRepository;

final class ListProductsTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return ProductRepository::class;
    }

    protected function entityTag(): string
    {
        return 'product';
    }

    protected function baseUrl(): string
    {
        return '/catalogo';
    }

    protected function title(): string
    {
        return 'Catalogo';
    }

    protected function statLabel(): string
    {
        return 'Totale prodotti';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo prodotto';
    }

    protected function createPermission(): ?string
    {
        return 'catalog.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'sku', 'label' => 'Codice'],
            ['key' => 'category', 'label' => 'Categoria'],
            ['key' => 'sellingPrice', 'label' => 'Prezzo'],
            ['key' => 'stockQuantity', 'label' => 'Scorte'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/catalogo/{id}', 'permission' => 'catalog.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_products';
    }

    public function description(): string
    {
        return "Mostra l'elenco dei prodotti del catalogo con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Catalogo';
    }

    public function menuSection(): ?string
    {
        return 'vendite';
    }

    public function requiredPermission(): ?string
    {
        return 'catalog.view';
    }

    public function triggers(): array
    {
        return ['catalogo', 'lista prodotti', 'elenco prodotti', 'prodotti'];
    }
}
