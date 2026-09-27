<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\ProductRepository;

final class EditProductTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return ProductRepository::class;
    }

    protected function packageName(): string
    {
        return 'catalog';
    }

    /** Il pacchetto si chiama 'catalog', ma l'entity dentro il manifest e' 'products' (potrebbe averne piu' d'una in futuro). */
    protected function manifestEntityKey(): string
    {
        return 'products';
    }

    protected function entityTag(): string
    {
        return 'product';
    }

    protected function baseUrl(): string
    {
        return '/catalogo';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo prodotto' : 'Modifica prodotto';
    }

    protected function searchColumns(): array
    {
        return ['name', 'sku', 'category', 'description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'sku', 'label' => 'Codice'],
            ['key' => 'category', 'label' => 'Categoria'],
        ];
    }

    public function name(): string
    {
        return 'edit_product';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un prodotto, cercato per id o per nome/codice/categoria/descrizione. "
            . "Usalo quando l'utente chiede di aprire o modificare uno specifico prodotto.";
    }

    public function requiredPermission(): ?string
    {
        return 'catalog.edit';
    }
}
