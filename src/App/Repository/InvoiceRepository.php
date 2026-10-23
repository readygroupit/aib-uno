<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Invoice;

final class InvoiceRepository extends AbstractRepository
{
    protected string $table = 'invoices';
    protected string $modelClass = Invoice::class;

    /**
     * Il prossimo progressivo dell'anno (le annullate restano nella
     * numerazione). Le pro forma hanno la loro numerazione, separata.
     */
    public function nextSequence(int $year, bool $proForma = false): int
    {
        $row = $this->db->fetchRow(
            "SELECT MAX(number_seq) AS n FROM invoices WHERE number_year = :y AND (COALESCE(document_type, 'fattura') = 'pro forma') = :pf",
            ['y' => $year, 'pf' => $proForma ? 1 : 0]
        );

        return (int) ($row['n'] ?? 0) + 1;
    }

    /** @return Invoice[] di un cliente, dalla piu' recente */
    public function findByCustomer(int $customerId): array
    {
        return $this->findAll(['customer_id' => $customerId], 'issue_date DESC, id DESC');
    }

    public function findByInstallment(int $installmentId): ?Invoice
    {
        $found = $this->findAll(['installment_id' => $installmentId], 'id DESC');

        return $found[0] ?? null;
    }

    /** @return Invoice[] emesse ma non ancora inviate allo SdI (le pro forma non vanno allo SdI) */
    public function findToSend(): array
    {
        return array_values(array_filter(
            $this->findAll(['stage' => 'emessa', 'sdi_status' => 'da inviare'], 'issue_date, id'),
            static fn (Invoice $i) => !$i->isProForma()
        ));
    }
}
