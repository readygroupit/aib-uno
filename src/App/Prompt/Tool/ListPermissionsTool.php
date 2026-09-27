<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\PermissionRepository;
use App\View\Component\DataTableComponent;

final class ListPermissionsTool implements PromptToolInterface
{
    private PermissionRepository $permissions;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->permissions = $container->get(PermissionRepository::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'list_permissions';
    }

    public function description(): string
    {
        return 'Mostra l\'elenco dei permessi definiti nel sistema.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Permessi';
    }

    public function menuSection(): ?string
    {
        return 'sistema';
    }

    public function menuCount(): ?string
    {
        return (string) $this->permissions->count();
    }

    public function requiredPermission(): ?string
    {
        return 'users.manage';
    }

    public function triggers(): array
    {
        return ['permessi', 'lista permessi', 'elenco permessi', 'permissions'];
    }

    /**
     * Stesso linguaggio delle sezioni del menu (vedi ShowMenuTool::SECTIONS)
     * ma per un asse diverso: li' e' il dominio/entita', qui e' il TIPO di
     * operazione - la stessa quadripartizione view/create/edit/delete che
     * ogni pacchetto su AbstractEntityController ormai usa per i propri
     * permessi (vedi AbstractEntityController::requiredPermissionForAction()).
     */
    private const CATEGORIES = [
        'view' => ['label' => 'Lettura', 'color' => 'teal'],
        'create' => ['label' => 'Creazione', 'color' => 'moss'],
        'edit' => ['label' => 'Modifica', 'color' => 'brass'],
        'delete' => ['label' => 'Eliminazione', 'color' => 'danger'],
    ];

    public function execute(array $input): array
    {
        $rows = $this->permissions->findAllWithDomain();

        $filterTabs = [['key' => null, 'label' => 'Tutte', 'color' => null]];
        foreach (self::CATEGORIES as $key => $meta) {
            $filterTabs[] = ['key' => $key, 'label' => $meta['label'], 'color' => $meta['color']];
        }

        $table = $this->dataTable->toData([
            'entity' => 'permission',
            'url' => '/permessi',
            'title' => 'Permessi',
            'columns' => [
                ['key' => 'domain', 'label' => 'Dominio'],
                ['key' => 'name', 'label' => 'Permesso'],
                ['key' => 'code', 'label' => 'Codice'],
                ['key' => 'category', 'label' => 'Categoria'],
            ],
            'rows' => $rows,
            'filterField' => 'category',
            'filterTabs' => $filterTabs,
            'searchable' => true,
            'actions' => [
                ['label' => 'Modifica', 'href' => '/permessi/{id}', 'permission' => 'users.manage'],
            ],
            'page' => 1,
            'perPage' => count($rows),
            'total' => count($rows),
        ]);

        return [$table];
    }
}
