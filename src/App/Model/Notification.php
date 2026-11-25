<?php

declare(strict_types=1);

namespace App\Model;

final class Notification extends AbstractModel
{
    public ?int $userId = null;
    public ?string $type = null;
    public ?string $title = null;
    public ?string $body = null;
    public ?string $url = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
    public ?int $inApp = null;
    public ?string $readAt = null;
    public ?int $emailDue = null;
    public ?string $emailedAt = null;
}
