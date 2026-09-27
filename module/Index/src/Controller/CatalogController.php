<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditProductTool;
use App\Prompt\Tool\ListProductsTool;
use App\Repository\ProductRepository;

final class CatalogController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return ProductRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditProductTool::class;
    }

    protected function listToolClass(): string
    {
        return ListProductsTool::class;
    }

    protected function packageName(): string
    {
        return 'catalog';
    }

    protected function manifestEntityKey(): string
    {
        return 'products';
    }

    protected function listPageTitle(): string
    {
        return 'Catalogo';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo prodotto';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica prodotto';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'in vendita'];
    }

    protected function createdMessage(): string
    {
        return 'Prodotto creato.';
    }
}
