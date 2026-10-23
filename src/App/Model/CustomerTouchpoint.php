<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerTouchpoint extends AbstractModel
{
    public ?int $customerId = null;
    public ?string $contactDate = null;
    public ?string $channel = null;
    public ?int $userId = null;
    public ?string $mood = null;
    public ?string $summary = null;
    public ?string $nextStep = null;
    public ?string $nextDate = null;
}
