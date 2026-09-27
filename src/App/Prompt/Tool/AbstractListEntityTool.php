<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\AbstractRepository;
use App\View\Component\DataTableComponent;
use App\View\Component\StatBoxComponent;

/**
 * Base comune per "lista con statistica totale" - era ListUsersTool e
 * ListLeadsTool quasi identici, differivano solo su repository/colonne/
 * testi. Una sottoclasse implementa solo i metodi astratti qui sotto
 * (dati specifici dell'entita') + i metodi di PromptToolInterface che
 * restano testo libero per forza (name/description/menuLabel/triggers/
 * requiredPermission - non generalizzabili senza perdere significato).
 *
 * Users/Permissions/Leads NON sono stati riportati a questa base (hanno
 * gia' una loro implementazione funzionante e testata): questa serve ai
 * pacchetti costruiti da qui in avanti. Un retrofit resta possibile in
 * futuro, a basso rischio, non necessario ora.
 */
abstract class AbstractListEntityTool implements PromptToolInterface
{
    protected AbstractRepository $repository;
    protected StatBoxComponent $statBox;
    protected DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->repository = $container->get($this->repositoryClass());
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Numero di pagina, default 1'],
            ],
        ];
    }

    /** @return class-string<AbstractRepository> */
    abstract protected function repositoryClass(): string;

    /** Tag usato per il contesto mandato a Claude/wizard (es. 'lead') - vedi DataTableComponent::entity. */
    abstract protected function entityTag(): string;

    /** Url della pagina reale di questa lista (es. '/contatti'). */
    abstract protected function baseUrl(): string;

    abstract protected function title(): string;

    abstract protected function statLabel(): string;

    /** Chiave sezione per la card del menu - vedi PromptToolInterface::menuSection(). */
    abstract public function menuSection(): ?string;

    /** Generico per ogni sottoclasse: un semplice conteggio righe attive. */
    public function menuCount(): ?string
    {
        return (string) $this->repository->count();
    }

    /** @return list<array{key: string, label: string}> */
    abstract protected function columns(): array;

    /** @return list<array{label: string, href: string, permission?: ?string}> */
    protected function actions(): array
    {
        return [];
    }

    protected function orderBy(): string
    {
        return 'id DESC';
    }

    protected function perPage(): int
    {
        return 10;
    }

    protected function createLabel(): ?string
    {
        return null;
    }

    /**
     * Permesso per il pulsante "Nuovo X", separato da requiredPermission()
     * (quello serve a VEDERE la lista, questo a mostrare l'azione di
     * CREAZIONE - non tutti hanno gli stessi poteri, vedi la stessa nota
     * su AbstractEntityController::requiredPermissionForAction()). Null
     * di default come createLabel(): un pacchetto senza pulsante "Nuovo"
     * non ha nemmeno bisogno di dichiarare un permesso per crearlo.
     */
    protected function createPermission(): ?string
    {
        return null;
    }

    protected function statComparison(): string
    {
        return 'attivi, esclusi quelli eliminati';
    }

    public function execute(array $input): array
    {
        $page = max(1, (int) ($input['page'] ?? 1));
        $result = $this->repository->paginate($page, $this->perPage(), [], $this->orderBy());
        $rows = array_map(static fn ($model) => $model->toDisplayArray(), $result['items']);

        $stats = $this->statBox->toData([
            'label' => $this->statLabel(),
            'value' => (string) $result['total'],
            'comparison' => $this->statComparison(),
            'sparkline' => [3, 3, 4, 4, 4, 5, $result['total']],
        ]);

        $table = $this->dataTable->toData([
            'entity' => $this->entityTag(),
            'url' => $this->baseUrl(),
            'title' => $this->title(),
            'createHref' => $this->createLabel() !== null ? $this->baseUrl() . '/nuovo' : null,
            'createLabel' => $this->createLabel(),
            'createPermission' => $this->createPermission(),
            'columns' => $this->columns(),
            'rows' => $rows,
            'actions' => $this->actions(),
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
        ]);

        return [$stats, $table];
    }
}
