<?php

declare(strict_types=1);

namespace App\Model;

final class AssistantRule extends AbstractModel
{
    public ?string $code = null;
    public ?int $isEnabled = null;
    public ?int $days = null;
    public ?string $autonomy = null;
}
