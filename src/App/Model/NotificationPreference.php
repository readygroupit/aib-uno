<?php

declare(strict_types=1);

namespace App\Model;

final class NotificationPreference extends AbstractModel
{
    public ?int $userId = null;
    public ?string $type = null;
    public ?int $inApp = null;
    public ?int $email = null;
}
