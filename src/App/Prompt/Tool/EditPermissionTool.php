<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\Permission;
use App\Prompt\PromptToolInterface;
use App\Repository\PermissionRepository;
use App\View\Component\DataTableComponent;
use App\View\Component\FormComponent;

/**
 * Seconda entita' sullo stesso schema di EditUserTool - serve a verificare
 * che il pattern (entity taggata sui componenti, tool di ricerca+modifica,
 * mapping locale verbo+contesto in PromptController) regga anche fuori
 * dal caso 'user' per cui e' stato scritto la prima volta, non solo a
 * costruire davvero la modifica dei permessi.
 *
 * 'code' non e' modificabile qui: e' l'identificativo usato ovunque nel
 * codice per i controlli ACL (es. AuthService::hasPermission('users.manage')) -
 * cambiarlo a caso da un form romperebbe quei controlli in modo silenzioso.
 */
final class EditPermissionTool implements PromptToolInterface
{
    private PermissionRepository $permissions;
    private FormComponent $form;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->permissions = $container->get(PermissionRepository::class);
        $this->form = $container->get(FormComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'edit_permission';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un permesso del sistema, cercato per id, codice, nome o "
            . "descrizione. Usalo quando l'utente chiede di aprire o modificare uno specifico permesso "
            . "(es. 'modifica il permesso users.manage', 'apri il permesso gestione utenti').";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => "Id del permesso, se gia' noto"],
                'query' => [
                    'type' => 'string',
                    'description' => "Codice, nome o descrizione (anche parziale) da cercare, se l'id non e' noto",
                ],
            ],
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
        return [];
    }

    public function execute(array $input): array
    {
        $id = isset($input['id']) ? (int) $input['id'] : null;
        $query = trim((string) ($input['query'] ?? ''));

        if ($id === null && $query !== '') {
            $matches = $this->permissions->searchByName($query);

            if (count($matches) === 0) {
                return [$this->emptyResult($query)];
            }

            if (count($matches) > 1) {
                return [$this->candidatesTable($matches, $query)];
            }

            $id = $matches[0]->id;
        }

        if ($id === null) {
            return [$this->emptyResult('')];
        }

        $permission = $this->permissions->find($id);
        if ($permission === null) {
            return [$this->emptyResult((string) $id)];
        }

        return [$this->buildForm($permission)];
    }

    public function buildForm(Permission $permission, ?string $message = null, ?string $messageType = null): array
    {
        return $this->form->toData([
            'entity' => 'permission',
            'title' => 'Modifica permesso',
            'action' => '/permessi/' . $permission->id,
            'fields' => [
                ['key' => 'code', 'label' => 'Codice', 'value' => $permission->code, 'type' => 'text', 'readonly' => true],
                ['key' => 'name', 'label' => 'Nome', 'value' => $permission->name, 'required' => true],
                ['key' => 'description', 'label' => 'Descrizione', 'value' => $permission->description],
            ],
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }

    private function candidatesTable(array $permissions, string $query): array
    {
        $rows = array_map(static fn (Permission $p) => $p->toDisplayArray(), $permissions);

        return $this->dataTable->toData([
            'entity' => 'permission',
            'title' => "Piu' permessi corrispondono a \u{ab}{$query}\u{bb}",
            'columns' => [
                ['key' => 'code', 'label' => 'Codice'],
                ['key' => 'name', 'label' => 'Nome'],
                ['key' => 'description', 'label' => 'Descrizione'],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => 'Apri', 'href' => '/permessi/{id}', 'permission' => 'users.manage'],
            ],
        ]);
    }

    private function emptyResult(string $query): array
    {
        return $this->dataTable->toData([
            'title' => 'Modifica permesso',
            'columns' => [],
            'rows' => [],
            'emptyMessage' => $query !== ''
                ? "Nessun permesso trovato per \u{ab}{$query}\u{bb}."
                : "Specifica un id o un nome di permesso da modificare.",
        ]);
    }
}
