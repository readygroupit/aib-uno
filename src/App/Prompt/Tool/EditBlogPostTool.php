<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\BlogPostRepository;

final class EditBlogPostTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return BlogPostRepository::class;
    }

    protected function packageName(): string
    {
        return 'blog';
    }

    protected function manifestEntityKey(): string
    {
        return 'posts';
    }

    protected function entityTag(): string
    {
        return 'blog_post';
    }

    protected function baseUrl(): string
    {
        return '/blog';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo articolo' : 'Modifica articolo';
    }

    protected function searchColumns(): array
    {
        return ['title', 'slug', 'excerpt'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    public function name(): string
    {
        return 'edit_blog_post';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un articolo del blog, cercato per id o per titolo/slug/estratto. "
            . "Usalo quando l'utente chiede di aprire o modificare uno specifico articolo.";
    }

    public function requiredPermission(): ?string
    {
        return 'blog.edit';
    }
}
