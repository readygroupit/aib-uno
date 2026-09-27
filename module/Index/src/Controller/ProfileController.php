<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\EditUserTool;
use App\Repository\UserRepository;
use App\Service\AuthService;

/**
 * "Il mio profilo": stessa scheda utente di UsersController::editAction,
 * ma sul proprio account (id preso dalla sessione, non dall'url) e senza
 * il permesso users.manage - qualunque utente autenticato deve poter
 * cambiare i propri dati e vedere un modo per sloggarsi, indipendente
 * dai permessi amministrativi.
 */
final class ProfileController extends AuthController
{
    public function editAction(): ?string
    {
        $userId = $this->container->get(AuthService::class)->currentUserId();

        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        $user = $userId !== null ? $users->find($userId) : null;

        if ($user === null) {
            $this->redirect('/login');

            return null;
        }

        /** @var EditUserTool $tool */
        $tool = $this->container->get(EditUserTool::class);

        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage([$tool->buildForm($user, null, null, '/profilo')], ['title' => 'Il mio profilo']);
        }

        $username = trim((string) $this->request->get('username', ''));
        $email = trim((string) $this->request->get('email', ''));

        if ($username === '' || $email === '') {
            $components = [$tool->buildForm($user, 'Username ed email sono obbligatori.', 'error', '/profilo')];
        } elseif ($users->usernameTakenByOther($username, $user->id)) {
            $components = [$tool->buildForm($user, "Lo username \u{ab}{$username}\u{bb} e' gia' in uso.", 'error', '/profilo')];
        } else {
            $users->update($user->id, [
                'username' => $username,
                'first_name' => trim((string) $this->request->get('first_name', '')) ?: null,
                'last_name' => trim((string) $this->request->get('last_name', '')) ?: null,
                'email' => $email,
            ]);

            $components = [$tool->buildForm($users->find($user->id), 'Modifiche salvate.', 'success', '/profilo')];
        }

        return $this->renderPage($components, ['title' => 'Il mio profilo']);
    }
}
