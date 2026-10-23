<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Expense;

final class ExpenseRepository extends AbstractRepository
{
    protected string $table = 'expenses';
    protected string $modelClass = Expense::class;

    /** @return Expense[] di una persona, dalle piu' recenti */
    public function findForUser(int $userId, int $limit = 30): array
    {
        return array_slice($this->findAll(['user_id' => $userId], 'expense_date DESC, id DESC'), 0, $limit);
    }

    /** Totale approvato (o rimborsato) di una commessa: il suo costo vivo. */
    public function totalForWorkOrder(int $workOrderId): float
    {
        $row = $this->db->fetchRow(
            "SELECT SUM(amount) AS total FROM expenses WHERE status != 0 AND work_order_id = :w AND stage IN ('approvata', 'rimborsata')",
            ['w' => $workOrderId]
        );

        return (float) ($row['total'] ?? 0);
    }

    /** @return array<string, float> 'Y-m' => totale approvato, ultimi $months mesi */
    public function totalPerMonth(int $months): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total FROM expenses
             WHERE status != 0 AND stage IN ('approvata', 'rimborsata') AND expense_date >= :from GROUP BY ym",
            ['from' => date('Y-m-01', strtotime('-' . ($months - 1) . ' months'))]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['ym']] = (float) $row['total'];
        }

        return $result;
    }
}
