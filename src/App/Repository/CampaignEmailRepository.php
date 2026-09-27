<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CampaignEmail;

final class CampaignEmailRepository extends AbstractRepository
{
    protected string $table = 'campaign_emails';
    protected string $modelClass = CampaignEmail::class;

    /** @return CampaignEmail[] */
    public function findByCampaign(int $campaignId): array
    {
        return $this->findAll(['campaign_id' => $campaignId], 'id ASC');
    }

    /** @return CampaignEmail[] */
    public function findQueued(): array
    {
        return $this->findAll(['stage' => 'in coda'], 'id ASC');
    }
}
