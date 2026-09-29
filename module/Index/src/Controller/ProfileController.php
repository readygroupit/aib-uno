<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\ShowProfileTool;
use App\Repository\UserRepository;
use App\Service\AuthService;

/**
 * "Il mio profilo": pagina a se' (vedi ShowProfileTool), non piu' un
 * riuso nudo della scheda utente admin. Due azioni POST distinte sulla
 * stessa pagina (dati personali / password) invece di un unico form con
 * tutto dentro - stessi principi di validazione gia' visti altrove
 * (nessun redirect, si ririsponde con lo stesso componente aggiornato).
 */
final class ProfileController extends AuthController
{
    public function editAction(): ?string
    {
        /** @var ShowProfileTool $tool */
        $tool = $this->container->get(ShowProfileTool::class);

        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage($tool->build(), ['title' => 'Il mio profilo']);
        }

        /** @var AuthService $auth */
        $auth = $this->container->get(AuthService::class);
        /** @var UserRepository $users */
        $users = $this->container->get(UserRepository::class);
        $userId = $auth->currentUserId();

        $username = trim((string) $this->request->get('username', ''));
        $email = trim((string) $this->request->get('email', ''));

        $fieldErrors = [];
        if ($username === '') {
            $fieldErrors['username'] = 'Lo username e\' obbligatorio.';
        } elseif ($users->usernameTakenByOther($username, $userId)) {
            $fieldErrors['username'] = "Lo username \u{ab}{$username}\u{bb} e' gia' in uso.";
        }
        if ($email === '') {
            $fieldErrors['email'] = 'L\'email e\' obbligatoria.';
        }

        if ($fieldErrors === []) {
            $users->update($userId, [
                'username' => $username,
                'email' => $email,
                'first_name' => trim((string) $this->request->get('first_name', '')) ?: null,
                'last_name' => trim((string) $this->request->get('last_name', '')) ?: null,
            ]);
        }

        $components = $tool->build([
            'message' => $fieldErrors === [] ? 'Modifiche salvate.' : null,
            'messageType' => $fieldErrors === [] ? 'success' : null,
            'fieldErrors' => $fieldErrors,
        ]);

        return $this->renderPage($components, ['title' => 'Il mio profilo']);
    }

    public function changePasswordAction(): ?string
    {
        /** @var ShowProfileTool $tool */
        $tool = $this->container->get(ShowProfileTool::class);
        /** @var AuthService $auth */
        $auth = $this->container->get(AuthService::class);
        $userId = (int) $auth->currentUserId();

        $current = (string) $this->request->get('current_password', '');
        $new = (string) $this->request->get('new_password', '');
        $confirm = (string) $this->request->get('new_password_confirm', '');

        if ($new !== $confirm) {
            $message = 'La conferma non corrisponde alla nuova password.';
            $messageType = 'error';
        } elseif (strlen($new) < 8) {
            $message = 'La nuova password deve avere almeno 8 caratteri.';
            $messageType = 'error';
        } elseif (!$auth->changePassword($userId, $current, $new)) {
            $message = 'Password attuale non corretta.';
            $messageType = 'error';
        } else {
            $message = 'Password cambiata.';
            $messageType = 'success';
        }

        $components = $tool->build([], ['message' => $message, 'messageType' => $messageType]);

        return $this->renderPage($components, ['title' => 'Il mio profilo']);
    }

    /**
     * "Ho capito, non mostrarmelo piu'" sul tour di benvenuto (vedi
     * onboarding.js) - chiamata dall'overlay su QUALUNQUE pagina, non
     * solo da /profilo: risponde solo JSON (l'overlay si chiude da se',
     * niente pagina da riverniciare).
     */
    public function dismissOnboardingAction(): void
    {
        /** @var AuthService $auth */
        $auth = $this->container->get(AuthService::class);
        $userId = $auth->currentUserId();

        if ($userId !== null) {
            $auth->dismissOnboarding($userId);
        }

        $this->json(['ok' => true]);
    }
}
