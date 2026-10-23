<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Repository\CustomerRepository;
use App\Repository\EditorialQuotaRepository;
use App\Repository\UserRepository;

/** Quanti contenuti a settimana per cliente e canale: e' il metro dei "buchi" del piano. */
final class ListEditorialQuotasTool extends AbstractListEntityTool
{
    private CustomerRepository $customers;
    private UserRepository $users;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->customers = $container->get(CustomerRepository::class);
        $this->users = $container->get(UserRepository::class);
    }

    protected function repositoryClass(): string
    {
        return EditorialQuotaRepository::class;
    }

    protected function entityTag(): string
    {
        return 'editorial_quota';
    }

    protected function baseUrl(): string
    {
        return '/piano-editoriale/contratti';
    }

    protected function title(): string
    {
        return 'Contratti social';
    }

    protected function statLabel(): string
    {
        return 'Contratti';
    }

    protected function statComparison(): string
    {
        return 'contenuti a settimana per cliente e canale';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo contratto';
    }

    protected function createPermission(): ?string
    {
        return 'editorial.create';
    }

    protected function decorateRows(array $rows): array
    {
        $names = [];
        foreach ($this->customers->findAll() as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }
        $people = [];
        foreach ($this->users->findAll() as $user) {
            $people[(int) $user->id] = trim("{$user->firstName} {$user->lastName}");
        }

        return array_map(static function (array $row) use ($names, $people) {
            $row['customer'] = $names[(int) $row['customerId']] ?? '';
            $row['amount'] = $row['perWeek'] . ' a settimana' . ($row['formats'] ? ' (' . $row['formats'] . ')' : '');
            $row['who'] = $people[(int) ($row['defaultUserId'] ?? 0)] ?? '';

            return $row;
        }, $rows);
    }

    protected function columns(): array
    {
        return [
            ['key' => 'customer', 'label' => 'Cliente'],
            ['key' => 'channel', 'label' => 'Canale'],
            ['key' => 'amount', 'label' => 'Quantita\''],
            ['key' => 'who', 'label' => 'Li prepara'],
        ];
    }

    protected function actions(): array
    {
        return [['label' => 'Modifica', 'href' => '/piano-editoriale/contratti/{id}', 'permission' => 'editorial.edit']];
    }

    protected function orderBy(): string
    {
        return 'customer_id, channel';
    }

    public function name(): string
    {
        return 'list_editorial_quotas';
    }

    public function description(): string
    {
        return "Contratti social: quanti contenuti a settimana per cliente e canale.";
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function menuSection(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return 'editorial.view';
    }

    public function triggers(): array
    {
        return ['contratti social', 'quantita post'];
    }
}
