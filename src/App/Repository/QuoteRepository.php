<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Quote;

final class QuoteRepository extends AbstractRepository
{
    protected string $table = 'quotes';
    protected string $modelClass = Quote::class;

    public function nextSequence(int $year): int
    {
        $row = $this->db->fetchRow('SELECT MAX(number_seq) AS n FROM quotes WHERE number_year = :y', ['y' => $year]);

        return (int) ($row['n'] ?? 0) + 1;
    }

    /** @return Quote[] inviati e scaduti, da segnare come tali */
    public function findSentExpired(string $today): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM quotes WHERE status != 0 AND stage = 'inviato' AND valid_until IS NOT NULL AND valid_until < :t",
            ['t' => $today]
        );

        return array_map(static fn (array $row) => Quote::fromArray($row), $rows);
    }

    /** @return Quote[] di un cliente in uno stato */
    public function findForCustomerInStage(int $customerId, string $stage): array
    {
        return $this->findAll(['customer_id' => $customerId, 'stage' => $stage], 'quote_date DESC, id DESC');
    }
}
