<?php

declare(strict_types=1);

namespace App\Package;

/**
 * Orchestratore: dato un insieme di pacchetti richiesti (con la relativa
 * selezione di campi) e una directory di progetto target, risolve
 * l'ordine di installazione e scrive su disco:
 *  - db/schema.sql: le CREATE TABLE di ogni pacchetto, in ordine di
 *    dipendenza, cumulativo;
 *  - db/migrations/pending/<package>__<entity>__<field>.sql: una per
 *    ogni campo del catalogo non scelto ora, pronta ma non eseguita;
 *  - db/seed/<package>.sql: copia del seed del pacchetto, se presente
 *    (solo 'geo' ne ha uno per ora - dati di riferimento, non scelte
 *    del cliente).
 *
 * Nessuna di queste query viene eseguita qui: questa classe genera solo
 * i file. L'esecuzione e' un passo separato e deliberato (vedi le note
 * nel manifest di 'customers' e 'geo' sulla regola "solo uno tocca lo
 * schema di un progetto").
 */
final class PackageInstallerRunner
{
    private PackageInstaller $installer;

    public function __construct(?PackageInstaller $installer = null)
    {
        $this->installer = $installer ?? new PackageInstaller();
    }

    /**
     * @param array<string,array> $installs packageName => selection (vedi PackageInstaller::build())
     */
    public function installMany(array $installs, string $targetDir): array
    {
        $order = $this->resolveOrder($installs);

        $dbDir = rtrim($targetDir, '/') . '/db';
        $pendingDir = $dbDir . '/migrations/pending';
        $seedDir = $dbDir . '/seed';

        foreach ([$dbDir, $pendingDir, $seedDir] as $dir) {
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \RuntimeException("Impossibile creare la directory: {$dir}");
            }
        }

        $schemaPath = $dbDir . '/schema.sql';
        $schema = fopen($schemaPath, 'w');

        $pendingFiles = [];
        $seedFiles = [];

        foreach ($order as $packageName) {
            $manifest = new PackageManifest($packageName);
            $selection = $installs[$packageName] ?? [];
            $result = $this->installer->build($manifest, $selection);

            fwrite($schema, "-- pacchetto: {$packageName}\n" . $result['core']);

            foreach ($result['pending'] as $key => $sql) {
                $file = "{$pendingDir}/{$packageName}__{$key}.sql";
                file_put_contents($file, $sql);
                $pendingFiles[] = $file;
            }

            $seedSource = ROOT_PATH . "/packages/{$packageName}/seed.sql";
            if (is_file($seedSource)) {
                $seedTarget = "{$seedDir}/{$packageName}.sql";
                copy($seedSource, $seedTarget);
                $seedFiles[] = $seedTarget;
            }
        }

        fclose($schema);

        return [
            'order' => $order,
            'schemaPath' => $schemaPath,
            'pendingFiles' => $pendingFiles,
            'seedFiles' => $seedFiles,
        ];
    }

    /**
     * L'ordine di installazione non segue solo 'dependsOn' (dichiarazione
     * statica nel manifest, valida per dipendenze strutturali che
     * servono sempre) ma anche le dipendenze che emergono dalla
     * selezione effettiva: un campo negoziabile con 'references' =>
     * ['package' => 'geo', ...] porta dentro 'geo' SOLO se quel campo e'
     * stato davvero scelto per questo progetto (o se e' un campo 'base',
     * quindi sempre incluso). Cosi' un progetto che installa 'customers'
     * senza mai scegliere 'municipality_id' non si porta dietro 'geo'
     * inutilmente - altrimenti si tradirebbe la regola "non creare
     * quello che non serve" alla prima dipendenza opzionale.
     *
     * @param array<string,array> $installs packageName => selection
     * Pubblico (non solo uso interno di installMany()): PackageCatalogService
     * lo riusa per risolvere le dipendenze quando si installa un singolo
     * package gia' in un progetto avviato, senza rigenerare uno
     * schema.sql cumulativo per tutti - vedi App\Package\MigrationWriter.
     *
     * @return string[] ordine di installazione, dipendenze incluse
     */
    public function resolveOrder(array $installs): array
    {
        $resolved = [];
        $visiting = [];

        $visit = function (string $packageName) use (&$visit, &$resolved, &$visiting, $installs): void {
            if (in_array($packageName, $resolved, true)) {
                return;
            }
            if (isset($visiting[$packageName])) {
                throw new \RuntimeException("Dipendenza circolare rilevata su '{$packageName}'");
            }
            $visiting[$packageName] = true;

            $manifest = new PackageManifest($packageName);
            $dependencies = $manifest->dependsOn();
            $selection = $installs[$packageName] ?? [];

            foreach ($manifest->entities() as $entityKey => $entity) {
                $chosen = $selection[$entityKey] ?? [];
                foreach ($entity['fields'] as $fieldKey => $field) {
                    $isSelected = ($field['base'] ?? false) || array_key_exists($fieldKey, $chosen);
                    // Un riferimento a una tabella dello STESSO pacchetto (es.
                    // agent_messages -> agents) non e' una dipendenza: l'ordine
                    // delle entity nel manifest basta.
                    if ($isSelected && isset($field['references']['package']) && $field['references']['package'] !== $packageName) {
                        $dependencies[] = $field['references']['package'];
                    }
                }
            }

            foreach (array_unique($dependencies) as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$packageName]);
            $resolved[] = $packageName;
        };

        foreach (array_keys($installs) as $packageName) {
            $visit($packageName);
        }

        return $resolved;
    }
}
