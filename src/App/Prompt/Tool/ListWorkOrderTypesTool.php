<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\WorkOrderTypeStepRepository;

final class ListWorkOrderTypesTool extends AbstractListEntityTool
{
    private WorkOrderTypeStepRepository $steps;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->steps = $container->get(WorkOrderTypeStepRepository::class);
    }

    protected function repositoryClass(): string
    {
        return WorkOrderTypeRepository::class;
    }

    protected function entityTag(): string
    {
        return 'work_order_type';
    }

    protected function baseUrl(): string
    {
        return '/tipi-commessa';
    }

    protected function title(): string
    {
        return 'Tipi di commessa';
    }

    protected function statLabel(): string
    {
        return 'Modelli di commessa';
    }

    protected function statComparison(): string
    {
        return 'ognuno con le sue fasi pronte';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo tipo di commessa';
    }

    protected function createPermission(): ?string
    {
        return 'work_orders.create';
    }

    protected function orderBy(): string
    {
        return 'name';
    }

    protected function perPage(): int
    {
        return 50;
    }

    protected function decorateRows(array $rows): array
    {
        $counts = $this->steps->countByType();

        return array_map(static function (array $row) use ($counts) {
            $row['steps'] = (string) ($counts[(int) $row['id']] ?? 0);
            $row['defaultDays'] = $row['defaultDays'] !== null ? $row['defaultDays'] . ' giorni' : '';
            $row['stage'] = ucfirst((string) $row['stage']);

            return $row;
        }, $rows);
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Tipo'],
            ['key' => 'steps', 'label' => 'Fasi'],
            ['key' => 'defaultDays', 'label' => 'Durata tipica'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    protected function actions(): array
    {
        return [['label' => 'Apri', 'href' => '/tipi-commessa/{id}', 'permission' => 'work_orders.view']];
    }

    public function name(): string
    {
        return 'list_work_order_types';
    }

    public function description(): string
    {
        return "Tipi di commessa (modelli con le fasi gia' pronte: sito, shooting, campagna...).";
    }

    public function menuLabel(): ?string
    {
        return 'Tipi di commessa';
    }

    public function menuSection(): ?string
    {
        return 'sistema';
    }

    public function requiredPermission(): ?string
    {
        return 'work_orders.view';
    }

    public function triggers(): array
    {
        return ['tipi di commessa', 'modelli di commessa'];
    }
}
