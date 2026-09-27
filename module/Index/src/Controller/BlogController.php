<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditBlogPostTool;
use App\Prompt\Tool\ListBlogPostsTool;
use App\Repository\BlogPostRepository;

final class BlogController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return BlogPostRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditBlogPostTool::class;
    }

    protected function listToolClass(): string
    {
        return ListBlogPostsTool::class;
    }

    protected function packageName(): string
    {
        return 'blog';
    }

    protected function manifestEntityKey(): string
    {
        return 'posts';
    }

    protected function listPageTitle(): string
    {
        return 'Blog';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo articolo';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica articolo';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'bozza'];
    }

    protected function createdMessage(): string
    {
        return 'Articolo creato.';
    }
}
