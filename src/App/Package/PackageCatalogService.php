<?php

declare(strict_types=1);

namespace App\Package;

use App\Core\Container;

/**
 * "E' installato?" e' una domanda sul FILESYSTEM, non sul database: un
 * package e' installato quando la sua migrazione 'core' e' stata
 * applicata, cioe' quando db/migrations/applied/<nome>__0_core.sql esiste
 * (bin/migrate.php sposta li' un file una volta eseguito con successo -
 * vedi MigrationWriter). Nessuna tabella di tracking separata: i file
 * sono gia' la fonte di verita' per tutto il resto del sistema di
 * package, restarci coerenti evita che uno stato in DB possa disallinearsi
 * da quello che e' successo davvero sul server.
 */
final class PackageCatalogService
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return array<int, array{
     *     name: string, label: string, installed: bool, category: ?string,
     *     description: string, dependsOn: string[],
     *     entities: array<int, array{key: string, table: string, baseFields: string[], optionalFields: string[]}>
     * }>
     */
    public function list(): array
    {
        return array_map(
            function (string $name) {
                $manifest = new PackageManifest($name);

                return [
                    'name' => $name,
                    'label' => $manifest->label(),
                    'installed' => $this->isInstalled($name),
                    'category' => $manifest->category(),
                    'description' => $manifest->description(),
                    'dependsOn' => $manifest->dependsOn(),
                    'entities' => $this->entitiesSummary($manifest),
                ];
            },
            $this->discoverPackageNames()
        );
    }

    /**
     * Etichette (non le chiavi grezze) dei campi di ogni entita' del
     * manifest, divise base/negoziabili - quello che davvero interessa
     * a chi guarda il dettaglio di un package ("cosa mi porto dentro"),
     * non la forma interna del manifest.
     *
     * @return array<int, array{key: string, table: string, baseFields: string[], optionalFields: string[]}>
     */
    private function entitiesSummary(PackageManifest $manifest): array
    {
        $summary = [];
        foreach ($manifest->entities() as $entityKey => $entity) {
            $base = [];
            $optional = [];
            foreach ($entity['fields'] as $field) {
                if ($field['base'] ?? false) {
                    $base[] = $field['label'] ?? '';
                } else {
                    $optional[] = $field['label'] ?? '';
                }
            }

            $summary[] = [
                'key' => $entityKey,
                'table' => $entity['table'] ?? $entityKey,
                'baseFields' => $base,
                'optionalFields' => $optional,
            ];
        }

        return $summary;
    }

    public function exists(string $name): bool
    {
        return is_file(ROOT_PATH . "/packages/{$name}/package.php");
    }

    public function isInstalled(string $name): bool
    {
        foreach (glob(ROOT_PATH . '/db/migrations/applied/*__0_core.sql') ?: [] as $file) {
            if ($this->packageNameFromCoreFile($file) === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Il nome del file applicato porta un prefisso timestamp+sequenza e un
     * suffisso "__0_core.sql" (vedi MigrationWriter per il perche' dello
     * "0_") - vanno tolti entrambi per confrontare il nome del package con
     * precisione. Un confronto "finisce con" (via glob a jolly) darebbe un
     * falso positivo, es. 'tasks' installato per colpa di 'ai_agent_tasks'.
     */
    private function packageNameFromCoreFile(string $path): string
    {
        return preg_replace('/^\d{14}_\d{2}_/', '', basename($path, '__0_core.sql'));
    }

    /** @return string[] */
    private function discoverPackageNames(): array
    {
        $dirs = glob(ROOT_PATH . '/packages/*', GLOB_ONLYDIR) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $dir) => is_file($dir . '/package.php') ? basename($dir) : null,
            $dirs
        )));
    }
}
