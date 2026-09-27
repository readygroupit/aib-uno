<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ConfigGroup;

final class ConfigGroupRepository extends AbstractRepository
{
    protected string $table = 'config_groups';
    protected string $modelClass = ConfigGroup::class;
}
