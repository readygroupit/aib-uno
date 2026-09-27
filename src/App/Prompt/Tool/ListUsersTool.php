<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\UserRepository;
use App\View\Component\DataTableComponent;
use App\View\Component\StatBoxComponent;

/**
 * Unica sorgente della schermata utenti: sia UsersController (click su
 * menu/route) sia il prompt la richiamano, cosi' non esistono due copie
 * della stessa logica di lista.
 */
final class ListUsersTool implements PromptToolInterface
{
    private UserRepository $users;
    private StatBoxComponent $statBox;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->users = $container->get(UserRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'list_users';
    }

    public function description(): string
    {
        return "Mostra l'elenco degli utenti del sistema con statistica totale.";
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

    public function menuLabel(): ?string
    {
        return 'Utenti';
    }

    public function menuSection(): ?string
    {
        return 'sistema';
    }

    public function menuCount(): ?string
    {
        return (string) $this->users->count();
    }

    public function requiredPermission(): ?string
    {
        return 'users.manage';
    }

    public function triggers(): array
    {
        return ['utenti', 'lista utenti', 'elenco utenti', 'user list', 'users'];
    }

    public function execute(array $input): array
    {
        $page = max(1, (int) ($input['page'] ?? 1));
        $result = $this->users->paginate($page, 10, [], 'id DESC');
        $rows = array_map(static fn ($user) => $user->toDisplayArray(), $result['items']);

        $stats = $this->statBox->toData([
            'label' => 'Totale utenti',
            'value' => (string) $result['total'],
            'comparison' => 'attivi, esclusi quelli eliminati',
            'sparkline' => [3, 3, 4, 4, 4, 5, $result['total']],
        ]);

        $table = $this->dataTable->toData([
            'entity' => 'user',
            'url' => '/utenti',
            'title' => 'Utenti',
            'columns' => [
                ['key' => 'username', 'label' => 'Username'],
                ['key' => 'firstName', 'label' => 'Nome'],
                ['key' => 'lastName', 'label' => 'Cognome'],
                ['key' => 'email', 'label' => 'Email'],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => 'Modifica', 'href' => '/utenti/{id}', 'permission' => 'users.manage'],
            ],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
        ]);

        return [$stats, $table];
    }
}
