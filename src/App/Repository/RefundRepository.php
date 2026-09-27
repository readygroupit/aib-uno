<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Refund;

final class RefundRepository extends AbstractRepository
{
    protected string $table = 'refunds';
    protected string $modelClass = Refund::class;

    /** Somma degli importi accettati (esclusi i rimborsi eliminati) - KPI "Economico" del brief Assilevi (12), usato dal Report. */
    public function sumAcceptedAmount(): float
    {
        $row = $this->db->fetchRow('SELECT SUM(amount_accepted) AS total FROM refunds WHERE status != 0');

        return (float) ($row['total'] ?? 0);
    }
}
