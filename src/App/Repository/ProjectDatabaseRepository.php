<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Le query che ProjectProvisioner esegue sul database di un progetto
 * APPENA CREATO - non quello di Uno, quindi niente AbstractRepository ne'
 * Container: riceve la connessione PDO del progetto. Sta qui per la
 * regola "SQL solo nei Repository".
 */
final class ProjectDatabaseRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public static function createDatabase(\PDO $server, string $dbName): void
    {
        $server->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public static function dropDatabase(\PDO $server, string $dbName): void
    {
        $server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    }

    public static function databaseExists(\PDO $server, string $dbName): bool
    {
        $statement = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :name');
        $statement->execute(['name' => $dbName]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function isEmpty(string $dbName): bool
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :name');
        $statement->execute(['name' => $dbName]);

        return (int) $statement->fetchColumn() === 0;
    }

    public function setForeignKeyChecks(bool $enabled): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=' . ($enabled ? '1' : '0'));
    }

    /**
     * seed.sql semina i permessi di TUTTI i pacchetti del catalogo: qui si
     * tolgono quelli dei pacchetti non installati, cosi' menu e Guida del
     * progetto (che filtrano per permesso) non mostrano funzioni senza
     * tabelle dietro.
     *
     * @param string[] $groupCodes codici di permission_groups da togliere
     */
    public function removePermissionGroups(array $groupCodes): void
    {
        if ($groupCodes === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($groupCodes), '?'));
        $permissionIds = "SELECT p.id FROM permissions p JOIN permission_groups g ON g.id = p.permission_group_id WHERE g.code IN ({$placeholders})";

        foreach (['profile_permissions', 'user_permissions'] as $table) {
            $statement = $this->pdo->prepare("DELETE FROM {$table} WHERE permission_id IN (SELECT id FROM ({$permissionIds}) AS ids)");
            $statement->execute($groupCodes);
        }
        $statement = $this->pdo->prepare("DELETE FROM permissions WHERE permission_group_id IN (SELECT id FROM permission_groups WHERE code IN ({$placeholders}))");
        $statement->execute($groupCodes);
        $statement = $this->pdo->prepare("DELETE FROM permission_groups WHERE code IN ({$placeholders})");
        $statement->execute($groupCodes);
    }

    /**
     * Sposta in avanti di $seconds ogni colonna data/ora delle tabelle
     * indicate: i dati demo restano "freschi" (un contatto "fermo da 2
     * giorni" lo e' ancora, a prescindere da quando si genera il progetto).
     *
     * @param string[] $tables
     */
    public function shiftDates(string $dbName, array $tables, int $seconds): void
    {
        if ($seconds <= 0 || $tables === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($tables), '?'));
        $statement = $this->pdo->prepare(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$placeholders}) AND DATA_TYPE IN ('datetime', 'date', 'timestamp')"
        );
        $statement->execute([$dbName, ...$tables]);

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $column) {
            $table = $column['TABLE_NAME'];
            $name = $column['COLUMN_NAME'];
            $this->pdo->exec("UPDATE `{$table}` SET `{$name}` = `{$name}` + INTERVAL {$seconds} SECOND WHERE `{$name}` IS NOT NULL");
        }
    }
}
