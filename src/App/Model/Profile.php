<?php

declare(strict_types=1);

namespace App\Model;

final class Profile extends AbstractModel
{
    public ?string $name = null;
    public ?string $description = null;
}
