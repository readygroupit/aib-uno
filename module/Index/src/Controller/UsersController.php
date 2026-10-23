<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\User;
use App\Prompt\Tool\EditUserTool;
use App\Prompt\Tool\ListUsersTool;
use App\Repository\ProfileRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;

/**
 * Utenti: elenco, nuovo, modifica (con profilo, password provvisoria,
 * link per scegliere la password, disattivazione). GET mostra il form,
 * POST lo rielabora sulla stessa rotta, mai un redirect: un errore di
 * validazione non perde i valori scritti.
 */
final class UsersController extends AuthController
{
    protected ?string $requiredPermission = 'users.manage';

    public function indexAction(): ?string
    {
        $page = max(1, (int) $this->param('page', 1));

        $components = $this->container->get(ListUsersTool::class)->execute(['page' => $page]);

        return $this->renderPage($components, ['title' => 'Utenti']);
    }

    public function newAction(): ?string
    {
        $tool = $this->container->get(EditUserTool::class);
        $blank = User::fromArray(['profile_id' => $this->defaultProfileId()]);
        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage([$tool->buildForm($blank)], ['title' => 'Nuovo utente']);
        }

        [$data, $error] = $this->collect(null);
        if ($error !== null) {
            return $this->renderPage([$tool->buildForm(User::fromArray($data), $error, 'error')], ['title' => 'Nuovo utente']);
        }
        $password = (string) $this->request->get('new_password', '');
        // Senza password provvisoria: una a caso, la persona sceglie la sua dal link.
        $data['password_hash'] = password_hash($password !== '' ? $password : bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $users = $this->container->get(UserRepository::class);
        $id = $users->insert($data);

        return $this->renderPage([$tool->buildForm($users->find($id), $password !== ''
            ? 'Utente creato con la password provvisoria: comunicala alla persona.'
            : 'Utente creato: manda il link per scegliere la password (serve l\'email configurata).', 'success')], ['title' => 'Modifica utente']);
    }

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

        [$data, $error] = $this->collect($id);
        if ($error !== null) {
            return $this->renderPage([$tool->buildForm($user, $error, 'error')], ['title' => 'Modifica utente']);
        }
        $password = (string) $this->request->get('new_password', '');
        if ($password !== '') {
            $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        // L'amministratore resta amministratore: altrimenti nessuno potrebbe piu' gestire gli utenti.
        if ($id === 1) {
            unset($data['profile_id']);
        }
        $users->update($id, $data);

        return $this->renderPage([$tool->buildForm($users->find($id), 'Modifiche salvate' . ($password !== '' ? ', password cambiata.' : '.'), 'success')], ['title' => 'Modifica utente']);
    }

    /** Il link per scegliere la password, alla sua email (lo stesso di "password dimenticata"). */
    public function inviteAction(): void
    {
        $user = $this->container->get(UserRepository::class)->find((int) $this->param('id'));
        if (!$user instanceof User || trim((string) $user->email) === '') {
            $this->json(['ok' => false, 'message' => 'Manca l\'email di questa persona.'], 422);

            return;
        }
        try {
            $this->container->get(AuthService::class)->requestPasswordReset((string) $user->email);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'message' => 'Email non partita: controlla la configurazione della posta.'], 500);

            return;
        }
        $this->json(['ok' => true, 'message' => 'Link mandato a ' . $user->email . ' (vale un\'ora).']);
    }

    public function deactivateAction(): void
    {
        $id = (int) $this->param('id');
        if ($id === 1 || $id === $this->container->get(AuthService::class)->currentUserId()) {
            $this->json(['ok' => false, 'message' => 'Questo utente non si puo\' disattivare.'], 422);

            return;
        }
        $this->container->get(UserRepository::class)->delete($id);
        $this->json(['ok' => true, 'message' => 'Accesso disattivato.', 'open' => '/utenti']);
    }

    /** @return array{0: array, 1: ?string} dati da salvare ed eventuale errore */
    private function collect(?int $id): array
    {
        $data = [
            'username' => trim((string) $this->request->get('username', '')),
            'email' => trim((string) $this->request->get('email', '')),
            'first_name' => trim((string) $this->request->get('first_name', '')),
            'last_name' => trim((string) $this->request->get('last_name', '')),
            'profile_id' => (int) $this->request->get('profile_id', 0) ?: null,
        ];
        $password = (string) $this->request->get('new_password', '');
        $users = $this->container->get(UserRepository::class);

        $error = match (true) {
            $data['username'] === '' || $data['email'] === '' => 'Username ed email sono obbligatori.',
            $data['first_name'] === '' || $data['last_name'] === '' => 'Nome e cognome sono obbligatori.',
            $users->usernameTakenByOther($data['username'], $id ?? 0) => "Lo username \u{ab}{$data['username']}\u{bb} e' gia' in uso.",
            $data['profile_id'] === null && $id !== 1 => 'Scegli il profilo.',
            $password !== '' && strlen($password) < 8 => 'La password deve avere almeno 8 caratteri.',
            default => null,
        };
        if ($data['profile_id'] === null) {
            unset($data['profile_id']);
        }

        return [$data, $error];
    }

    /** Per un utente nuovo, il profilo piu' "semplice": l'ultimo creato che non sia l'amministratore. */
    private function defaultProfileId(): ?int
    {
        $profiles = array_filter($this->container->get(ProfileRepository::class)->findAll([], 'id DESC'), static fn ($p) => (int) $p->id !== 1);

        return $profiles !== [] ? (int) array_values($profiles)[0]->id : 1;
    }
}
