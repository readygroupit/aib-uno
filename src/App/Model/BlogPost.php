<?php

declare(strict_types=1);

namespace App\Model;

final class BlogPost extends AbstractModel
{
    public ?string $title = null;
    public ?string $slug = null;
    public ?string $excerpt = null;
    public ?string $body = null;
    public ?string $coverImageUrl = null;
    public ?int $authorUserId = null;
    public ?string $stage = null;
    public ?string $publishedAt = null;
}
