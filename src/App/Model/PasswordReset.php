<?php

declare(strict_types=1);

namespace App\Model;

final class PasswordReset extends AbstractModel
{
    public ?int $userId = null;
    public ?string $tokenHash = null;
    public ?string $expiresAt = null;
    public ?string $usedAt = null;
}
