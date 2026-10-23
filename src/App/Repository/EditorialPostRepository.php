<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\EditorialPost;

final class EditorialPostRepository extends AbstractRepository
{
    protected string $table = 'editorial_posts';
    protected string $modelClass = EditorialPost::class;

    /** @return EditorialPost[] con uscita tra due giorni (inclusi) */
    public function findBetween(string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM editorial_posts WHERE status != 0 AND publish_date BETWEEN :from AND :to ORDER BY publish_date, publish_time, id',
            ['from' => $from, 'to' => $to]
        );

        return array_map(static fn (array $row) => EditorialPost::fromArray($row), $rows);
    }

    /** @return EditorialPost[] di un cliente in uno stato */
    public function findForCustomerInStage(int $customerId, string $stage): array
    {
        return $this->findAll(['customer_id' => $customerId, 'stage' => $stage], 'publish_date, id');
    }

    /** @return array<string, int> "clienteId|canale" => contenuti non scartati nel periodo */
    public function countPerCustomerChannel(string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            "SELECT customer_id, channel, COUNT(*) AS n FROM editorial_posts
             WHERE status != 0 AND stage != 'scartato' AND publish_date BETWEEN :from AND :to
             GROUP BY customer_id, channel",
            ['from' => $from, 'to' => $to]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['customer_id'] . '|' . $row['channel']] = (int) $row['n'];
        }

        return $result;
    }
}
