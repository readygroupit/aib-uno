<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Communication;

final class CommunicationRepository extends AbstractRepository
{
    protected string $table = 'communications';
    protected string $modelClass = Communication::class;

    /** Comunicazioni attive inviate da $since in poi (formato 'Y-m-d H:i:s') - usato dal Report. */
    public function countSentSince(string $since): int
    {
        $row = $this->db->fetchRow(
            'SELECT COUNT(*) AS total FROM communications WHERE status != 0 AND sent_at >= :since',
            ['since' => $since]
        );

        return (int) ($row['total'] ?? 0);
    }
}
