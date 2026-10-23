<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Support\ItalianFormat as F;

final class ListCustomerServicesTool extends AbstractListEntityTool
{
    private CustomerRepository $customers;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->customers = $container->get(CustomerRepository::class);
    }

    protected function decorateRows(array $rows): array
    {
        $names = [];
        foreach ($this->customers->findAll() as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }
        $today = date('Y-m-d');

        return array_map(static function (array $row) use ($names, $today) {
            $row['customer'] = $names[(int) ($row['customerId'] ?? 0)] ?? '';
            $row['stage'] = ucfirst((string) ($row['stage'] ?? ''));
            $row['recurrence'] = ($row['billingType'] ?? null) === 'ricorrente' ? ucfirst((string) $row['recurrence']) : 'Una tantum';
            $row['price'] = F::euro((float) ($row['price'] ?? 0));
            $row['renewalDate'] = !empty($row['renewalDate']) ? F::shortDate($row['renewalDate'], $today) : '';

            return $row;
        }, $rows);
    }

    protected function repositoryClass(): string
    {
        return CustomerServiceRepository::class;
    }

    protected function entityTag(): string
    {
        return 'customer_service';
    }

    protected function baseUrl(): string
    {
        return '/servizi-clienti';
    }

    protected function title(): string
    {
        return 'Servizi venduti';
    }

    protected function statLabel(): string
    {
        return 'Servizi venduti';
    }

    protected function statComparison(): string
    {
        return 'attivi, sospesi e chiusi';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo servizio a un cliente';
    }

    protected function createPermission(): ?string
    {
        return 'customer_services.create';
    }

    protected function orderBy(): string
    {
        return "FIELD(stage, 'attivo', 'sospeso', 'disdetto', 'concluso'), renewal_date IS NULL, renewal_date, id DESC";
    }

    protected function perPage(): int
    {
        return 25;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'customer', 'label' => 'Cliente'],
            ['key' => 'title', 'label' => 'Servizio'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'recurrence', 'label' => 'Rinnovo'],
            ['key' => 'price', 'label' => 'Importo'],
            ['key' => 'renewalDate', 'label' => 'Prossimo rinnovo'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Apri', 'href' => '/servizi-clienti/{id}', 'permission' => 'customer_services.view'],
        ];
    }

    public function name(): string
    {
        return 'list_customer_services';
    }

    public function description(): string
    {
        return "Elenco di tutti i servizi venduti ai clienti, con stato, importo e prossimo rinnovo.";
    }

    public function menuLabel(): ?string
    {
        return 'Servizi venduti';
    }

    public function menuSection(): ?string
    {
        return 'vendite';
    }

    public function requiredPermission(): ?string
    {
        return 'customer_services.view';
    }

    public function triggers(): array
    {
        return ['servizi venduti', 'servizi dei clienti', 'contratti'];
    }
}
