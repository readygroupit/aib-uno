<?php

declare(strict_types=1);

namespace App\Model;

final class SeasonalEvent extends AbstractModel
{
    public ?string $name = null;
    public ?int $month = null;
    public ?int $day = null;
    public ?int $easterOffset = null;
    public ?int $leadDays = null;
    public ?string $keywords = null;
    public ?string $idea = null;
    public ?int $isEnabled = null;
}
