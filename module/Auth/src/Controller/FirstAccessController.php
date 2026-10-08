<?php

declare(strict_types=1);

namespace Auth\Controller;

use App\Controller\AbstractController;
use App\Service\AuthService;
use App\Service\FirstAccessService;

/**
 * /primo-accesso di un progetto generato da Uno (vedi FirstAccessService):
 * senza il link con lo slug cifrato non si fa nulla; con il link si sceglie
 * l'accesso dell'amministratore e poi si completano i dati del progetto.
 */
final class FirstAccessController extends AbstractController
{
    protected function allowedDuringSetup(): bool
    {
        return true;
    }

    public function indexAction(): string
    {
        $setup = $this->container->get(FirstAccessService::class);
        $auth = $this->container->get(AuthService::class);

        if (!$setup->isPending()) {
            $this->redirect('/');

            return '';
        }

        $token = (string) $this->request->get('t', '');
        if ($token !== '' && $setup->tokenMatches($token)) {
            $_SESSION['firstAccess'] = true;
            $this->redirect('/primo-accesso');

            return '';
        }

        $accountDone = $setup->accountDone();
        $loggedAdmin = $auth->currentUserId() === FirstAccessService::ADMIN_USER_ID;
        if (!($_SESSION['firstAccess'] ?? false) && !($accountDone && $loggedAdmin)) {
            return $this->render('first-access/locked', ['title' => 'Primo accesso', 'accountDone' => $accountDone]);
        }

        $step = (string) $this->request->get('step', $accountDone ? 'project' : 'account');
        if ($step === 'project' && (!$accountDone || !$loggedAdmin)) {
            $step = 'account';
        }

        $error = null;
        if ($this->request->getMethod() === 'POST') {
            $error = $step === 'account'
                ? $setup->saveAccount(
                    (string) $this->request->get('first_name', ''),
                    (string) $this->request->get('last_name', ''),
                    (string) $this->request->get('email', ''),
                    (string) $this->request->get('password', ''),
                    (string) $this->request->get('password_confirm', '')
                )
                : $setup->saveProject(
                    array_map('strval', array_intersect_key($_POST, FirstAccessService::PROJECT_FIELDS)),
                    $this->request->file('logo')
                );

            if ($error === null) {
                unset($_SESSION['firstAccess']);
                $this->redirect($step === 'account' ? '/primo-accesso?step=project' : '/');

                return '';
            }
        }

        if ($step === 'account') {
            return $this->render('first-access/account', [
                'title' => 'Primo accesso',
                'error' => $error,
                'values' => $error !== null ? [
                    'firstName' => (string) $this->request->get('first_name', ''),
                    'lastName' => (string) $this->request->get('last_name', ''),
                    'email' => (string) $this->request->get('email', ''),
                ] : $setup->accountValues(),
                'accountDone' => $accountDone,
            ]);
        }

        return $this->render('first-access/project', [
            'title' => 'Primo accesso',
            'error' => $error,
            'values' => $setup->projectValues(),
            'fields' => FirstAccessService::PROJECT_FIELDS,
        ]);
    }
}
