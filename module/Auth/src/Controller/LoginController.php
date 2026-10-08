<?php

declare(strict_types=1);

namespace Auth\Controller;

use App\Controller\AbstractController;
use App\Service\AuthService;
use App\Service\FirstAccessService;

final class LoginController extends AbstractController
{
    /**
     * Durante il primo accesso il login serve solo all'amministratore che
     * ha gia' scelto la sua password (passo 'account' fatto) e ha chiuso
     * il browser prima di finire: poi torna al wizard.
     */
    protected function allowedDuringSetup(): bool
    {
        return $this->action === 'logout' || $this->container->get(FirstAccessService::class)->accountDone();
    }

    public function loginAction(): string
    {
        $auth = $this->container->get(AuthService::class);

        if ($auth->isAuthenticated()) {
            $this->redirect('/');

            return '';
        }

        $error = null;
        // Di default spuntato (comodo per la maggior parte degli accessi),
        // ma dopo un tentativo fallito riflette cio' che era stato inviato
        // invece di ripristinare lo stato iniziale.
        $remember = true;
        if ($this->request->getMethod() === 'POST') {
            $identifier = (string) $this->request->get('identifier', '');
            $password = (string) $this->request->get('password', '');
            $remember = (string) $this->request->get('remember', '') !== '';

            if ($auth->login($identifier, $password) !== null) {
                // "Resta connesso": durata vera del cookie di sessione (30
                // giorni), non solo un checkbox che non fa nulla.
                // session_set_cookie_params() non e' utilizzabile qui: PHP
                // lo vieta a sessione gia' attiva (public/index.php chiama
                // session_start() prima ancora di arrivare a questo
                // controller) - genera solo un warning silenzioso, il
                // cookie resterebbe quello "di sessione" di sempre.
                // setcookie() invece rimanda lo STESSO cookie di sessione
                // (stesso nome, stesso id appena rigenerato da login()) con
                // una scadenza esplicita: il browser tiene per buono
                // l'ultimo Set-Cookie per quel nome, questo sovrascrive
                // quello automatico di session_start(). Senza spunta resta
                // il default (cookie che scade alla chiusura del browser).
                if ($remember) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), session_id(), [
                        'expires' => time() + 60 * 60 * 24 * 30,
                        'path' => $params['path'] ?: '/',
                        'domain' => $params['domain'],
                        'secure' => $params['secure'],
                        'httponly' => $params['httponly'],
                        'samesite' => $params['samesite'] ?: 'Lax',
                    ]);
                }

                $this->redirect('/');

                return '';
            }

            $error = 'Credenziali non valide.';
        }

        return $this->render('login/login', ['title' => 'Accedi', 'error' => $error, 'remember' => $remember]);
    }

    public function logoutAction(): void
    {
        $this->container->get(AuthService::class)->logout();
        $this->redirect('/login');
    }

    public function forgotAction(): string
    {
        $sent = false;
        if ($this->request->getMethod() === 'POST') {
            $identifier = (string) $this->request->get('identifier', '');
            $this->container->get(AuthService::class)->requestPasswordReset($identifier);
            $sent = true;
        }

        return $this->render('login/forgot', ['title' => 'Password dimenticata', 'sent' => $sent]);
    }

    public function resetAction(): string
    {
        $token = (string) $this->param('token', '');
        $auth = $this->container->get(AuthService::class);
        $error = null;
        $done = false;

        if ($this->request->getMethod() === 'POST') {
            $password = (string) $this->request->get('password', '');

            if (strlen($password) < 8) {
                $error = 'La password deve avere almeno 8 caratteri.';
            } elseif ($auth->resetPassword($token, $password)) {
                $done = true;
            } else {
                $error = 'Link non valido o scaduto.';
            }
        }

        return $this->render('login/reset', [
            'title' => 'Imposta nuova password',
            'token' => $token,
            'error' => $error,
            'done' => $done,
        ]);
    }
}
