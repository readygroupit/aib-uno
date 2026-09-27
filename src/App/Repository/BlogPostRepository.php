<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\BlogPost;

final class BlogPostRepository extends AbstractRepository
{
    protected string $table = 'blog_posts';
    protected string $modelClass = BlogPost::class;
}
