<?php

declare(strict_types=1);

namespace App\Package;

/**
 * Scrive i file di migrazione per UN package (gia' risolto nell'ordine di
 * dipendenza corretto da PackageInstallerRunner::resolveOrder()) dentro
 * db/migrations/pending/ - non esegue MAI nulla sul database: l'esecuzione
 * e' compito esclusivo di bin/migrate.php, un passo separato e deliberato
 * (vedi li' il perche' - stesso principio gia' scritto per
 * PackageInstallerRunner, qui applicato all'aggiunta di UN package a un
 * progetto gia' avviato, non alla generazione iniziale di uno intero).
 *
 * Prefisso "<timestamp>_<sequenza>_" sul nome file: bin/migrate.php applica
 * i file in ordine alfabetico, e i nomi dei package non sono
 * alfabeticamente nell'ordine di dipendenza giusto (es. potrebbe servire
 * 'geo' prima di 'customers' anche se G viene dopo C) - il prefisso lo
 * garantisce senza che bin/migrate.php debba sapere nulla di dipendenze.
 */
final class MigrationWriter
{
    private readonly PackageInstaller $installer;

    public function __construct(\App\Core\Container $container)
    {
        $this->installer = new PackageInstaller();
    }

    /**
     * @param string[] $order pacchetti nell'ordine gia' risolto da
     *     PackageInstallerRunner::resolveOrder() - quelli gia' installati
     *     vanno filtrati PRIMA di chiamare questo metodo (il chiamante
     *     decide con quale selezione installare ciascuno, non questa
     *     classe).
     * @param array<string, array> $selections packageName => selezione
     *     (vedi PackageManifest::defaultSelection() / PackageInstaller::build())
     * @param string|null $projectRoot radice del progetto in cui scrivere
     *     (default: questo) - ProjectProvisioner la usa per un progetto nuovo
     * @return string[] percorsi dei file scritti, nell'ordine di applicazione
     */
    public function writeBatch(array $order, array $selections, ?string $projectRoot = null): array
    {
        $dir = rtrim($projectRoot ?? ROOT_PATH, '/') . '/db/migrations/pending';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Impossibile creare la directory: {$dir}");
        }

        $timestamp = date('YmdHis');
        $written = [];

        foreach (array_values($order) as $index => $packageName) {
            $prefix = $timestamp . '_' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '_';
            $manifest = new PackageManifest($packageName);
            $result = $this->installer->build($manifest, $selections[$packageName] ?? []);

            // "0_core" / "1_<campo>": non solo "core" vs il nome del campo,
            // perche' in ordine alfabetico il nome di una tabella puo'
            // finire PRIMA di "core" (es. "cases" < "core", la ' a' di
            // "cases" precede la 'o' di "core") - bin/migrate.php ordina i
            // file alfabeticamente, quindi senza questa cifra un ALTER
            // TABLE poteva finire eseguito prima della CREATE TABLE dello
            // stesso pacchetto (successo davvero in prova, con 'cases').
            if (trim($result['core']) !== '') {
                $file = "{$dir}/{$prefix}{$packageName}__0_core.sql";
                file_put_contents($file, $result['core']);
                $written[] = $file;
            }

            foreach ($result['pending'] as $key => $sql) {
                $file = "{$dir}/{$prefix}{$packageName}__1_{$key}.sql";
                file_put_contents($file, $sql);
                $written[] = $file;
            }
        }

        return $written;
    }
}
