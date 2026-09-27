<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Service;

final class ServiceRepository extends AbstractRepository
{
    protected string $table = 'services';
    protected string $modelClass = Service::class;
}
