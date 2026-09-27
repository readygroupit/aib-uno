<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Config;

final class ConfigRepository extends AbstractRepository
{
    protected string $table = 'configs';
    protected string $modelClass = Config::class;
}
