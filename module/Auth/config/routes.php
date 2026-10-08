<?php

declare(strict_types=1);

use Auth\Controller\FirstAccessController;
use Auth\Controller\LoginController;

return [
    'login' => [
        'path' => '/login',
        'controller' => LoginController::class,
        'action' => 'login',
    ],
    'logout' => [
        'path' => '/logout',
        'controller' => LoginController::class,
        'action' => 'logout',
    ],
    'password-forgot' => [
        'path' => '/password/dimenticata',
        'controller' => LoginController::class,
        'action' => 'forgot',
    ],
    'first-access' => [
        'path' => '/primo-accesso',
        'controller' => FirstAccessController::class,
        'action' => 'index',
    ],
    'password-reset' => [
        'path' => '/password/reset/:token',
        'controller' => LoginController::class,
        'action' => 'reset',
    ],
];
