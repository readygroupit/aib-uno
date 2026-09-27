<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\BlogPostRepository;

final class ListBlogPostsTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return BlogPostRepository::class;
    }

    protected function entityTag(): string
    {
        return 'blog_post';
    }

    protected function baseUrl(): string
    {
        return '/blog';
    }

    protected function title(): string
    {
        return 'Blog';
    }

    protected function statLabel(): string
    {
        return 'Totale articoli';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo articolo';
    }

    protected function createPermission(): ?string
    {
        return 'blog.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'publishedAt', 'label' => 'Pubblicazione'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/blog/{id}', 'permission' => 'blog.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_blog_posts';
    }

    public function description(): string
    {
        return "Mostra l'elenco degli articoli del blog con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Blog';
    }

    public function menuSection(): ?string
    {
        return 'contenuti';
    }

    public function requiredPermission(): ?string
    {
        return 'blog.view';
    }

    public function triggers(): array
    {
        return ['blog', 'lista articoli', 'elenco articoli'];
    }
}
