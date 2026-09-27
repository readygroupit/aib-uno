<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Campaign;

final class CampaignRepository extends AbstractRepository
{
    protected string $table = 'campaigns';
    protected string $modelClass = Campaign::class;
}
