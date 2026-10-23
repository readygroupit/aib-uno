<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\Profile;
use App\Prompt\Tool\EditProfileTool;
use App\Prompt\Tool\ShowProfilesTool;
use App\Repository\PermissionRepository;
use App\Repository\ProfilePermissionRepository;
use App\Repository\ProfileRepository;
use App\Service\AuthService;

/**
 * Profili (ruoli) e i loro permessi: elenco, nuovo, modifica. Come gli
 * utenti, dietro users.manage.
 */
final class ProfilesController extends AuthController
{
    protected ?string $requiredPermission = 'users.manage';

    public function indexAction(): ?string
    {
        return $this->renderPage($this->container->get(ShowProfilesTool::class)->execute([]), ['title' => 'Profili e permessi']);
    }

    public function newAction(): ?string
    {
        $tool = $this->container->get(EditProfileTool::class);
        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage([$tool->buildForm(null)], ['title' => 'Nuovo profilo']);
        }
        $name = trim((string) $this->request->get('name', ''));
        if ($name === '') {
            return $this->renderPage([$tool->buildForm(null, 'Dai un nome al profilo.', 'error')], ['title' => 'Nuovo profilo']);
        }
        $id = $this->container->get(ProfileRepository::class)->insert(['name' => $name, 'description' => trim((string) $this->request->get('description', '')) ?: null]);
        $this->savePermissions($id);
        $profile = $this->container->get(ProfileRepository::class)->find($id);

        return $this->renderPage([$tool->buildForm($profile, 'Profilo creato.', 'success')], ['title' => 'Profilo']);
    }

    public function editAction(): ?string
    {
        $tool = $this->container->get(EditProfileTool::class);
        $profiles = $this->container->get(ProfileRepository::class);
        $profile = $profiles->find((int) $this->param('id'));
        if (!$profile instanceof Profile) {
            return $this->renderPage($this->container->get(ShowProfilesTool::class)->execute([]), ['title' => 'Profili e permessi']);
        }
        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage([$tool->buildForm($profile)], ['title' => 'Profilo']);
        }
        $name = trim((string) $this->request->get('name', ''));
        if ($name === '') {
            return $this->renderPage([$tool->buildForm($profile, 'Il nome e\' obbligatorio.', 'error')], ['title' => 'Profilo']);
        }
        $profiles->update((int) $profile->id, ['name' => $name, 'description' => trim((string) $this->request->get('description', '')) ?: null]);
        $this->savePermissions((int) $profile->id);

        return $this->renderPage([$tool->buildForm($profiles->find((int) $profile->id), 'Profilo salvato: i permessi nuovi valgono dal prossimo accesso.', 'success')], ['title' => 'Profilo']);
    }

    /** Le caselle di ogni area: arrivano come elenchi di id separati da virgola. */
    private function savePermissions(int $profileId): void
    {
        $ids = [];
        foreach (array_keys($this->container->get(EditProfileTool::class)->areas()) as $key) {
            foreach (explode(',', (string) $this->request->get($key, '')) as $id) {
                if (ctype_digit(trim($id))) {
                    $ids[] = (int) $id;
                }
            }
        }
        // L'amministratore non si toglie la gestione degli utenti: resterebbe chiuso fuori.
        if ($profileId === 1) {
            foreach ($this->container->get(PermissionRepository::class)->findAll(['code' => 'users.manage']) as $permission) {
                $ids[] = (int) $permission->id;
            }
        }
        $this->container->get(ProfilePermissionRepository::class)->replaceForProfile($profileId, $ids, $this->container->get(AuthService::class)->currentUserId());
    }
}
