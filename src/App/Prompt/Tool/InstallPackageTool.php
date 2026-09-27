<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Package\MigrationWriter;
use App\Package\PackageCatalogService;
use App\Package\PackageInstallerRunner;
use App\Package\PackageManifest;
use App\Prompt\PromptToolInterface;
use App\View\Component\DataTableComponent;

/**
 * Genera i file di migrazione per installare un package del catalogo (e le
 * sue dipendenze non ancora installate in questo progetto) - NON esegue
 * nulla sul database. Scrive in db/migrations/pending/, pronti per
 * bin/migrate.php (vedi li' e MigrationWriter per il perche' di questa
 * separazione: l'esecuzione e' un passo deliberato, puo' avvenire altrove
 * e in un altro momento rispetto a dove/quando si e' scelto cosa
 * installare).
 *
 * Selezione dei campi negoziabili: usa PackageManifest::defaultSelection()
 * (solo quelli marcati 'defaultRequired'), non una negoziazione
 * interattiva campo per campo - quella e' un pezzo di UI a parte, non
 * ancora costruito. I campi non inclusi ora restano comunque pronti tra i
 * pending, installabili in un secondo momento senza rigenerare nulla.
 */
final class InstallPackageTool implements PromptToolInterface
{
    private PackageCatalogService $catalog;
    private MigrationWriter $writer;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->catalog = $container->get(PackageCatalogService::class);
        $this->writer = $container->get(MigrationWriter::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'install_package';
    }

    public function description(): string
    {
        return "Genera i file di migrazione per installare un package del catalogo e le sue dipendenze "
            . "mancanti. Non tocca il database: crea file in db/migrations/pending/ da applicare con "
            . "bin/migrate.php. Usalo quando l'operatore chiede di installare o attivare un package "
            . "(es. 'installa il pacchetto clienti').";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'package' => [
                    'type' => 'string',
                    'description' => "Nome del package da installare, es. 'geo', 'customers'",
                ],
            ],
            'required' => ['package'],
        ];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function menuSection(): ?string
    {
        return null;
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return 'users.manage';
    }

    public function triggers(): array
    {
        // Nome del package sempre dinamico, stesso motivo di edit_user/
        // edit_permission: nessuna frase fissa possibile, passa da Claude
        // (o dal mapping locale verbo+contesto in PromptController).
        return [];
    }

    public function execute(array $input): array
    {
        $name = trim((string) ($input['package'] ?? ''));

        if ($name === '' || !$this->catalog->exists($name)) {
            return [$this->message("Package \u{ab}{$name}\u{bb} non trovato nel catalogo.")];
        }

        if ($this->catalog->isInstalled($name)) {
            return [$this->message("Il package '{$name}' e' gia' installato in questo progetto.")];
        }

        $requestedSelection = (new PackageManifest($name))->defaultSelection();
        $order = (new PackageInstallerRunner())->resolveOrder([$name => $requestedSelection]);

        $toInstall = array_values(array_filter(
            $order,
            fn (string $packageName) => !$this->catalog->isInstalled($packageName)
        ));

        if ($toInstall === []) {
            return [$this->message("Il package '{$name}' e' gia' installato in questo progetto.")];
        }

        $selections = [$name => $requestedSelection];
        $written = $this->writer->writeBatch($toInstall, $selections);

        $fileNames = implode(', ', array_map('basename', $written));
        $extra = count($toInstall) > 1
            ? ' (comprese le dipendenze non ancora installate: ' . implode(', ', array_diff($toInstall, [$name])) . ')'
            : '';

        return [$this->message(
            "Generati " . count($written) . " file di migrazione{$extra}: {$fileNames}. "
            . "Esegui bin/migrate.php per applicarli al database."
        )];
    }

    private function message(string $text): array
    {
        return $this->dataTable->toData([
            'title' => 'Installazione package',
            'columns' => [],
            'rows' => [],
            'emptyMessage' => $text,
        ]);
    }
}
