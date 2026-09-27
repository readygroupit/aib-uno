<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\EditUserTool;
use App\Prompt\Tool\ListUsersTool;
use App\Repository\UserRepository;

final class UsersController extends AuthController
{
    protected ?string $requiredPermission = 'users.manage';

    public function indexAction(): ?string
    {
        $page = max(1, (int) $this->param('page', 1));

        $components = $this->container->get(ListUsersTool::class)->execute(['page' => $page]);

        return $this->renderPage($components, ['title' => 'Utenti']);
    }

    /**
     * Stessa fonte sia per il caricamento diretto (click "Modifica" nella
     * lista) sia per il salvataggio (POST sulla stessa route, come
     * Auth\Controller\LoginController) - GET mostra il form, POST lo
     * rielabora e ririsponde con esito, mai un redirect: cosi' un errore
     * di validazione non perde i valori scritti dall'operatore.
     */
    public function editAction(): ?string
    {
        $id = (int) $this->param('id');

        /** @var EditUserTool $tool */
        $tool = $this->container->get(EditUserTool::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);

        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica utente']);
        }

        $user = $users->find($id);
        if ($user === null) {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica utente']);
        }

        $username = trim((string) $this->request->get('username', ''));
        $email = trim((string) $this->request->get('email', ''));

        if ($username === '' || $email === '') {
            $components = [$tool->buildForm($user, 'Username ed email sono obbligatori.', 'error')];
        } elseif ($users->usernameTakenByOther($username, $id)) {
            $components = [$tool->buildForm($user, "Lo username \u{ab}{$username}\u{bb} e' gia' in uso.", 'error')];
        } else {
            $users->update($id, [
                'username' => $username,
                'first_name' => trim((string) $this->request->get('first_name', '')) ?: null,
                'last_name' => trim((string) $this->request->get('last_name', '')) ?: null,
                'email' => $email,
            ]);

            $components = [$tool->buildForm($users->find($id), 'Modifiche salvate.', 'success')];
        }

        return $this->renderPage($components, ['title' => 'Modifica utente']);
    }
}
