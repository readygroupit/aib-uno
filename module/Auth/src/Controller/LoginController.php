<?php

declare(strict_types=1);

namespace Auth\Controller;

use App\Controller\AbstractController;
use App\Service\AuthService;

final class LoginController extends AbstractController
{
    public function loginAction(): string
    {
        $auth = $this->container->get(AuthService::class);

        if ($auth->isAuthenticated()) {
            $this->redirect('/');

            return '';
        }

        $error = null;
        if ($this->request->getMethod() === 'POST') {
            $identifier = (string) $this->request->get('identifier', '');
            $password = (string) $this->request->get('password', '');

            if ($auth->login($identifier, $password) !== null) {
                $this->redirect('/');

                return '';
            }

            $error = 'Credenziali non valide.';
        }

        return $this->render('login/login', ['title' => 'Accedi', 'error' => $error]);
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
