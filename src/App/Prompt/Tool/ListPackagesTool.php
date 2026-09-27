<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Package\PackageCatalogService;
use App\Prompt\PromptToolInterface;
use App\Support\Sections;
use App\View\Component\DataTableComponent;

/**
 * Elenca il catalogo dei package (packages/*\/package.php) con lo stato di
 * installazione in QUESTO progetto - non esegue e non modifica nulla,
 * solo lettura. Vedi InstallPackageTool per l'installazione vera.
 */
final class ListPackagesTool implements PromptToolInterface
{
    private PackageCatalogService $catalog;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->catalog = $container->get(PackageCatalogService::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'list_packages';
    }

    public function description(): string
    {
        return "Mostra il catalogo dei package disponibili e quali sono gia' installati in questo progetto.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Package';
    }

    public function menuSection(): ?string
    {
        return 'sistema';
    }

    /** "installati/totali", non un conteggio puro - l'unica voce di menu dove il numero grezzo da solo direbbe poco. */
    public function menuCount(): ?string
    {
        $packages = $this->catalog->list();
        $installed = count(array_filter($packages, static fn (array $p) => $p['installed']));

        return $installed . '/' . count($packages);
    }

    public function requiredPermission(): ?string
    {
        // Riusa 'users.manage' come permesso "amministrazione" invece di
        // introdurne uno nuovo da seminare/concedere solo per questo -
        // se in futuro serve un permesso dedicato al provisioning, va
        // creato e assegnato esplicitamente, non deciso di nascosto qui.
        return 'users.manage';
    }

    public function triggers(): array
    {
        return ['package', 'lista package', 'elenco package', 'pacchetti', 'lista pacchetti'];
    }

    public function execute(array $input): array
    {
        $rows = array_map(
            static fn (array $p) => [
                'name' => $p['name'],
                'label' => $p['label'],
                'section' => $p['category'],
                'stage' => $p['installed'] ? 'Installato' : 'Da installare',
                // Non colonne da mostrare in tabella (nessuna voce in
                // 'columns' qui sotto le referenzia) - solo dati che la
                // riga si porta dietro per openPackageDetailModal() in
                // data-table.js, che li legge direttamente da qui invece
                // di fare una richiesta separata.
                'description' => $p['description'],
                'installed' => $p['installed'],
                'dependsOn' => $p['dependsOn'],
                'entities' => $p['entities'],
            ],
            $this->catalog->list()
        );

        $table = $this->dataTable->toData([
            'entity' => 'package',
            'title' => 'Package',
            'columns' => [
                ['key' => 'label', 'label' => 'Nome'],
                ['key' => 'section', 'label' => 'Sezione'],
                ['key' => 'stage', 'label' => 'Stato'],
            ],
            'rows' => $rows,
            'filterField' => 'section',
            'filterTabs' => $this->sectionFilterTabs(),
            'searchable' => true,
            'actions' => [
                ['label' => 'Dettagli', 'kind' => 'detail'],
            ],
        ]);

        return [$table];
    }

    /** @return list<array{key: ?string, label: string, color: ?string}> */
    private function sectionFilterTabs(): array
    {
        $tabs = [['key' => null, 'label' => 'Tutte', 'color' => null]];
        foreach (Sections::ALL as $key => $meta) {
            $tabs[] = ['key' => $key, 'label' => $meta['label'], 'color' => $meta['color']];
        }

        return $tabs;
    }
}
