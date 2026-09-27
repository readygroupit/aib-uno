<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Communication;

final class CommunicationRepository extends AbstractRepository
{
    protected string $table = 'communications';
    protected string $modelClass = Communication::class;
}
