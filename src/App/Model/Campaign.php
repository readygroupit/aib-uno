<?php

declare(strict_types=1);

namespace App\Model;

final class Campaign extends AbstractModel
{
    public ?string $name = null;
    public ?string $subject = null;
    public ?string $body = null;
    public ?string $stage = null;
    public ?string $scheduledAt = null;
    public ?string $targetSegment = null;
    public ?int $sentCount = null;
}
