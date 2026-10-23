<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Container;
use App\Service\DbService;

/**
 * Solo per preparare i dati demo dei preset nel database di sviluppo di
 * Uno: svuota le tabelle demo e le esporta come INSERT per demo.sql. Non
 * arriva nei progetti generati (vedi ProjectProvisioner::CONFIGURATOR_FILES).
 */
final class DemoDataRepository
{
    private DbService $db;

    public function __construct(Container $container)
    {
        $this->db = $container->get(DbService::class);
    }

    /** @param string[] $tables nell'ordine in cui vanno svuotate (prima chi riferisce) */
    public function clear(array $tables): void
    {
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->db->execute("DELETE FROM `{$table}`");
            $this->db->execute("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
        }
        $this->db->execute('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Una INSERT per riga, colonne generate escluse.
     *
     * @param array<string, array<string, mixed>> $overrides tabella => colonna => valore fisso (es. utente 1)
     */
    public function exportInserts(array $tables, array $overrides = []): string
    {
        $pdo = $this->db->pdo();
        $sql = '';
        foreach ($tables as $table) {
            $generated = array_column($this->db->fetchAll(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND EXTRA LIKE '%GENERATED%'",
                ['t' => $table]
            ), 'COLUMN_NAME');

            foreach ($this->db->fetchAll("SELECT * FROM `{$table}` ORDER BY id") as $row) {
                foreach ($generated as $column) {
                    unset($row[$column]);
                }
                foreach ($overrides[$table] ?? [] as $column => $value) {
                    if (array_key_exists($column, $row) && $row[$column] !== null) {
                        $row[$column] = $value;
                    }
                }
                $columns = implode(', ', array_map(static fn (string $c) => "`{$c}`", array_keys($row)));
                $values = implode(',', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row));
                $sql .= "INSERT INTO `{$table}` ({$columns}) VALUES ({$values});\n";
            }
            $sql .= "\n";
        }

        return $sql;
    }
}
