<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\EditorialQuota;

final class EditorialQuotaRepository extends AbstractRepository
{
    protected string $table = 'editorial_quotas';
    protected string $modelClass = EditorialQuota::class;
}
