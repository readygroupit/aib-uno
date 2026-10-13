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

    /** Importi accettati registrati tra due date (es. questa settimana e la precedente, per il confronto del report). */
    public function sumAcceptedBetween(string $from, string $to): float
    {
        $row = $this->db->fetchRow(
            'SELECT SUM(amount_accepted) AS total FROM refunds WHERE status != 0 AND created_at >= :from AND created_at < :to',
            ['from' => $from, 'to' => $to]
        );

        return (float) ($row['total'] ?? 0);
    }

    /** @return float[] valore accettato accumulato a fine di ognuno degli ultimi 7 giorni (oggi per ultimo) */
    public function sumAcceptedCumulativeLast7(): array
    {
        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $row = $this->db->fetchRow(
                'SELECT SUM(amount_accepted) AS total FROM refunds WHERE status != 0 AND created_at < :until',
                ['until' => date('Y-m-d', strtotime('-' . ($i - 1) . ' days'))]
            );
            $series[] = (float) ($row['total'] ?? 0);
        }

        return $series;
    }
}
