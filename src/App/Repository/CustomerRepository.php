<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Customer;

final class CustomerRepository extends AbstractRepository
{
    protected string $table = 'customers';
    protected string $modelClass = Customer::class;

    /**
     * Ogni cliente con la sua pratica PIU' RECENTE (se ne ha una) e i dati
     * collegati: volo/vettore/disservizio dal lead di origine, importo dal
     * rimborso, completezza documenti aggregata - vedi ListCustomersTool,
     * l'unico chiamante. Un cliente puo' avere piu' pratiche nel tempo, qui
     * se ne mostra una sola (la piu' recente) per riga: e' la vista
     * "situazione attuale del cliente", non uno storico completo (quello
     * resta il pacchetto Pratiche).
     *
     * N+1 deliberato (una query per cliente, non un unico JOIN enorme):
     * i volumi qui sono clienti-per-progetto (decine, non migliaia), la
     * leggibilita' vale piu' della query singola - stesso principio gia'
     * seguito altrove in questo file.
     *
     * @return list<array>
     */
    public function findWithClaimSummary(): array
    {
        $customers = $this->findAll([], 'id DESC');

        $rows = [];
        foreach ($customers as $customer) {
            $case = $this->db->fetchRow(
                'SELECT * FROM cases WHERE primary_customer_id = :id AND status != 0 ORDER BY id DESC LIMIT 1',
                ['id' => $customer->id]
            );

            $lead = null;
            $refund = null;
            $docsTotal = 0;
            $docsComplete = 0;

            if ($case !== null) {
                if ($case['lead_id'] !== null) {
                    $lead = $this->db->fetchRow('SELECT * FROM leads WHERE id = :id', ['id' => $case['lead_id']]);
                }
                $refund = $this->db->fetchRow(
                    'SELECT * FROM refunds WHERE case_id = :id AND status != 0 ORDER BY id DESC LIMIT 1',
                    ['id' => $case['id']]
                );
                $docsTotal = (int) ($this->db->fetchRow(
                    'SELECT COUNT(*) AS n FROM document_requests WHERE case_id = :id AND status != 0',
                    ['id' => $case['id']]
                )['n'] ?? 0);
                $docsComplete = (int) ($this->db->fetchRow(
                    "SELECT COUNT(*) AS n FROM document_requests WHERE case_id = :id AND status != 0 AND completeness_status = 'completo'",
                    ['id' => $case['id']]
                )['n'] ?? 0);
            }

            $rows[] = [
                'customer' => $customer,
                'case' => $case,
                'lead' => $lead,
                'refund' => $refund,
                'docsTotal' => $docsTotal,
                'docsComplete' => $docsComplete,
            ];
        }

        return $rows;
    }
}
