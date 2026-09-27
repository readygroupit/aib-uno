<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Intervention;

final class InterventionRepository extends AbstractRepository
{
    protected string $table = 'interventions';
    protected string $modelClass = Intervention::class;
}
