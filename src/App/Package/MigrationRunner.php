<?php

declare(strict_types=1);

namespace App\Package;

/**
 * Applica i file .sql di una cartella "pending" a un database e li sposta
 * in "applied" (lo spostamento E' il tracking, vedi
 * PackageCatalogService::isInstalled()). Usato da bin/migrate.php sul
 * progetto corrente e da ProjectProvisioner su un progetto appena creato -
 * per questo riceve PDO e cartelle invece di prenderli dal container.
 */
final class MigrationRunner
{
    /**
     * @return string[] nomi dei file applicati, in ordine
     * @throws \RuntimeException alla prima migrazione fallita (le precedenti restano applicate)
     */
    public function apply(\PDO $pdo, string $pendingDir, string $appliedDir): array
    {
        foreach ([$pendingDir, $appliedDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException("Impossibile creare la directory: {$dir}");
            }
        }

        $files = glob($pendingDir . '/*.sql') ?: [];
        sort($files);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new \RuntimeException("Impossibile leggere {$name}");
            }

            // Ogni file e' generato da PackageInstaller (mai testo arbitrario):
            // puo' contenere piu' istruzioni DDL concatenate, separate qui a
            // mano invece di affidarsi al multi-statement di PDO.
            try {
                foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $statement) {
                    $pdo->exec($statement);
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException("Migrazione {$name} fallita: {$e->getMessage()}", 0, $e);
            }

            rename($file, $appliedDir . '/' . $name);
            $applied[] = $name;
        }

        return $applied;
    }
}
