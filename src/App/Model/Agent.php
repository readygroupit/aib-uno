<?php

declare(strict_types=1);

namespace App\Model;

final class Agent extends AbstractModel
{
    public ?string $code = null;
    public ?string $name = null;
    public ?string $role = null;
    public ?string $bio = null;
    public ?int $isEnabled = null;
    public ?string $schedule = null;
    public ?string $autonomy = null;
    public ?string $ruleText = null;
    public ?string $lastRunAt = null;
}
