<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Exception\ForbiddenException;
use App\Service\AuthService;

/**
 * Base per i controller che richiedono un utente autenticato. Le
 * sottoclassi possono impostare $requiredPermission per bloccare l'intera
 * action a chi non ha quel permesso (403), oltre al semplice check di
 * login (redirect a /login).
 */
abstract class AuthController extends AbstractController
{
    protected ?string $requiredPermission = null;

    public function init(): void
    {
        parent::init();
        if ($this->response->isFinished()) {
            return;
        }

        $auth = $this->container->get(AuthService::class);

        if (!$auth->isAuthenticated()) {
            $this->redirect('/login');

            return;
        }

        $required = $this->requiredPermissionForAction($this->action);
        if ($required !== null && !$auth->hasPermission($required)) {
            throw new ForbiddenException("Permesso mancante: {$required}");
        }
    }

    /**
     * Default: lo stesso $requiredPermission per ogni azione (comportamento
     * di sempre, ogni controller che non lo sovrascrive resta identico).
     * AbstractEntityController lo sovrascrive per chiedere un permesso
     * diverso per index/new/edit/delete invece di uno unico per l'intero
     * controller - non tutti hanno gli stessi poteri su una CRUD (vedi li').
     */
    protected function requiredPermissionForAction(string $action): ?string
    {
        return $this->requiredPermission;
    }
}
