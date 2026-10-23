<?php

declare(strict_types=1);

namespace App\Model;

final class EditorialQuota extends AbstractModel
{
    public ?int $customerId = null;
    public ?string $channel = null;
    public ?int $perWeek = null;
    public ?string $formats = null;
    public ?int $defaultUserId = null;
}
