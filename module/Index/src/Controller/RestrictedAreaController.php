<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;

final class RestrictedAreaController extends AuthController
{
    protected ?string $requiredPermission = 'users.manage';

    public function indexAction(): string
    {
        return $this->render('restricted-area/index', ['title' => 'Area riservata']);
    }
}
