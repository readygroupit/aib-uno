<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\EditPermissionTool;
use App\Prompt\Tool\ListPermissionsTool;
use App\Repository\PermissionRepository;

/**
 * Stesso schema di UsersController - vedi li' per il perche' delle
 * scelte (GET/POST sulla stessa route, mai un redirect). 'code' non
 * viene mai letto dal POST: e' in sola lettura nel form (vedi
 * EditPermissionTool), qui non c'e' nemmeno il rischio di leggerlo per
 * sbaglio dalla request.
 */
final class PermissionsController extends AuthController
{
    protected ?string $requiredPermission = 'users.manage';

    /**
     * Prima non esisteva una pagina vera per la lista permessi
     * (raggiungibile solo dal prompt) - senza una route, "lista
     * permessi" non poteva mai corrispondere a una navigazione reale
     * (vedi il nuovo campo 'page' su ListPermissionsTool).
     */
    public function indexAction(): ?string
    {
        $components = $this->container->get(ListPermissionsTool::class)->execute([]);

        return $this->renderPage($components, ['title' => 'Permessi']);
    }

    public function editAction(): ?string
    {
        $id = (int) $this->param('id');

        /** @var EditPermissionTool $tool */
        $tool = $this->container->get(EditPermissionTool::class);
        /** @var PermissionRepository $permissions */
        $permissions = $this->container->get(PermissionRepository::class);

        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica permesso']);
        }

        $permission = $permissions->find($id);
        if ($permission === null) {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica permesso']);
        }

        $name = trim((string) $this->request->get('name', ''));

        if ($name === '') {
            $components = [$tool->buildForm($permission, 'Il nome e\' obbligatorio.', 'error')];
        } else {
            $permissions->update($id, [
                'name' => $name,
                'description' => trim((string) $this->request->get('description', '')) ?: null,
            ]);

            $components = [$tool->buildForm($permissions->find($id), 'Modifiche salvate.', 'success')];
        }

        return $this->renderPage($components, ['title' => 'Modifica permesso']);
    }
}
