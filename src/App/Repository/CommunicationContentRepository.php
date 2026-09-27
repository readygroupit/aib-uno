<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CommunicationContent;

final class CommunicationContentRepository extends AbstractRepository
{
    protected string $table = 'communication_contents';
    protected string $modelClass = CommunicationContent::class;

    public function findByCommunicationId(int $communicationId): ?CommunicationContent
    {
        $rows = $this->findAll(['communication_id' => $communicationId]);

        return $rows[0] ?? null;
    }
}
