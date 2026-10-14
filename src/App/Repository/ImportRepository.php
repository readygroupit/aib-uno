<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Container;
use App\Service\DbService;

/** SQL dell'importazione dal foglio (App\Import\SheetImporter). */
final class ImportRepository
{
    /** Tabelle con dati di lavoro: quelle svuotate da "togli i dati demo". */
    private const WORK_TABLES = ['agent_messages', 'refunds', 'document_requests', 'communications', 'tasks', 'cases', 'customers', 'leads'];

    private DbService $db;

    public function __construct(Container $container)
    {
        $this->db = $container->get(DbService::class);
    }

    /**
     * Toglie i dati demo (eliminazione logica, status = 0) e disattiva gli
     * operatori di esempio (password non valida, vedi lo script demo).
     *
     * @return array<string, int> tabella => righe tolte
     */
    public function removeDemoData(): array
    {
        $removed = [];
        foreach (self::WORK_TABLES as $table) {
            if ($this->db->fetchRow('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t', ['t' => $table]) === null) {
                continue;
            }
            $removed[$table] = $this->db->execute("UPDATE `{$table}` SET status = 0 WHERE status != 0");
        }
        $this->db->execute("UPDATE users SET status = 0 WHERE status != 0 AND password_hash = '!demo-senza-accesso'");

        return $removed;
    }

    /** @return list<array{id: int, original_submission_id: ?string, email: ?string, phone: ?string, mobile_phone: ?string, first_name: ?string, last_name: ?string, created_at: string}> */
    public function leadsIndex(): array
    {
        return $this->db->fetchAll('SELECT id, original_submission_id, email, phone, mobile_phone, first_name, last_name, created_at FROM leads WHERE status != 0');
    }

    /** @return list<array{id: int, email: ?string, phone: ?string, first_name: ?string, last_name: ?string}> */
    public function customersIndex(): array
    {
        return $this->db->fetchAll('SELECT id, email, phone, first_name, last_name FROM customers WHERE status != 0');
    }

    /** @return array<int, int> lead_id => case_id */
    public function casesByLead(): array
    {
        $result = [];
        foreach ($this->db->fetchAll('SELECT id, lead_id FROM cases WHERE status != 0 AND lead_id IS NOT NULL') as $row) {
            $result[(int) $row['lead_id']] = (int) $row['id'];
        }

        return $result;
    }

    /** @return array<string, true> chiavi "case_id|importo|data" dei rimborsi gia' presenti */
    public function refundKeys(): array
    {
        $keys = [];
        foreach ($this->db->fetchAll('SELECT case_id, amount_accepted, DATE(created_at) AS d FROM refunds WHERE status != 0') as $row) {
            $keys[$row['case_id'] . '|' . (float) $row['amount_accepted'] . '|' . $row['d']] = true;
        }

        return $keys;
    }

    /** @return array<string, true> chiavi "case_id|tipo|data" dei documenti gia' presenti */
    public function documentKeys(): array
    {
        $keys = [];
        foreach ($this->db->fetchAll('SELECT case_id, document_type, DATE(requested_at) AS d FROM document_requests WHERE status != 0') as $row) {
            $keys[$row['case_id'] . '|' . $row['document_type'] . '|' . $row['d']] = true;
        }

        return $keys;
    }

    /** La data vera del foglio (insert() timbra sempre "adesso"). */
    public function setCreatedAt(string $table, int $id, string $date): void
    {
        $this->db->execute("UPDATE `{$table}` SET created_at = :d WHERE id = :id", ['d' => $date, 'id' => $id]);
    }

    /** @return list<array{id: int, first_name: string, last_name: string, username: string}> */
    public function users(): array
    {
        return $this->db->fetchAll('SELECT id, first_name, last_name, username FROM users WHERE status != 0');
    }
}
