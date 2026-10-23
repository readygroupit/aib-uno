<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Installment;

final class InstallmentRepository extends AbstractRepository
{
    protected string $table = 'installments';
    protected string $modelClass = Installment::class;

    /** @return Installment[] dalla scadenza piu' vicina */
    public function findByCustomer(int $customerId): array
    {
        return $this->findAll(['customer_id' => $customerId], "FIELD(stage, 'da incassare', 'incassato', 'annullato'), due_date");
    }

    /** @return Installment[] */
    public function findByService(int $customerServiceId): array
    {
        return $this->findAll(['customer_service_id' => $customerServiceId], 'due_date');
    }

    /** @return Installment[] da incassare con scadenza fino a $until (anche quelle passate), la piu' vecchia prima */
    public function findOpenUntil(string $until): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM installments
             WHERE status != 0 AND stage = 'da incassare' AND due_date <= :until
             ORDER BY due_date, id",
            ['until' => $until]
        );

        return array_map(static fn (array $row) => Installment::fromArray($row), $rows);
    }

    /**
     * Da fatturare: in scadenza fino a $until, o incassati da $since, e
     * ancora senza fattura.
     *
     * @return Installment[]
     */
    public function findWithoutInvoice(string $until, string $since): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM installments
             WHERE status != 0 AND invoice_id IS NULL
               AND ((stage = 'da incassare' AND due_date <= :until) OR (stage = 'incassato' AND paid_at >= :since))
             ORDER BY due_date, id",
            ['until' => $until, 'since' => $since]
        );

        return array_map(static fn (array $row) => Installment::fromArray($row), $rows);
    }

    /** @return Installment[] incassate tra due date (incluse), la piu' recente prima */
    public function findPaidBetween(string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM installments
             WHERE status != 0 AND stage = 'incassato' AND paid_at BETWEEN :from AND :to
             ORDER BY paid_at DESC, id DESC",
            ['from' => $from, 'to' => $to]
        );

        return array_map(static fn (array $row) => Installment::fromArray($row), $rows);
    }

    /**
     * Per ogni cliente: quanto deve ancora pagare, quanto e' gia' scaduto e
     * la prossima scadenza - una query sola per tutta la lista clienti.
     *
     * @return array<int, array{open: float, overdue: float, overdueCount: int, nextDue: ?string, oldestOverdue: ?string}>
     */
    public function openSummaryByCustomer(string $today): array
    {
        $rows = $this->db->fetchAll(
            "SELECT customer_id,
                    SUM(amount) AS open_total,
                    SUM(CASE WHEN due_date < :today THEN amount ELSE 0 END) AS overdue_total,
                    SUM(CASE WHEN due_date < :today2 THEN 1 ELSE 0 END) AS overdue_count,
                    MIN(CASE WHEN due_date >= :today3 THEN due_date END) AS next_due,
                    MIN(CASE WHEN due_date < :today4 THEN due_date END) AS oldest_overdue
             FROM installments
             WHERE status != 0 AND stage = 'da incassare'
             GROUP BY customer_id",
            ['today' => $today, 'today2' => $today, 'today3' => $today, 'today4' => $today]
        );

        $summary = [];
        foreach ($rows as $row) {
            $summary[(int) $row['customer_id']] = [
                'open' => (float) $row['open_total'],
                'overdue' => (float) $row['overdue_total'],
                'overdueCount' => (int) $row['overdue_count'],
                'nextDue' => $row['next_due'],
                'oldestOverdue' => $row['oldest_overdue'],
            ];
        }

        return $summary;
    }

    /** Somma incassata per mese (chiave 'Y-m') negli ultimi $months mesi, mese corrente compreso. */
    public function paidPerMonth(int $months, string $today): array
    {
        $from = date('Y-m-01', strtotime(date('Y-m-01', strtotime($today)) . ' -' . ($months - 1) . ' months'));
        $rows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, SUM(amount) AS total
             FROM installments
             WHERE status != 0 AND stage = 'incassato' AND paid_at >= :from AND paid_at <= :today
             GROUP BY ym",
            ['from' => $from, 'today' => $today]
        );
        $byMonth = array_column($rows, 'total', 'ym');

        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime(date('Y-m-01', strtotime($today)) . " -{$i} months"));
            $result[$ym] = (float) ($byMonth[$ym] ?? 0);
        }

        return $result;
    }

    public function hasPaidForService(int $customerServiceId): bool
    {
        $row = $this->db->fetchRow(
            "SELECT COUNT(*) AS n FROM installments WHERE status != 0 AND stage = 'incassato' AND customer_service_id = :id",
            ['id' => $customerServiceId]
        );

        return (int) ($row['n'] ?? 0) > 0;
    }

    /** Elimina (logicamente) le rate ancora da incassare di un servizio: prima di rigenerarle da un piano cambiato. */
    public function deleteOpenForService(int $customerServiceId): int
    {
        $count = 0;
        foreach ($this->findAll(['customer_service_id' => $customerServiceId, 'stage' => 'da incassare']) as $installment) {
            $count += $this->delete((int) $installment->id);
        }

        return $count;
    }
}
