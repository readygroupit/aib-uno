<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Assistant\ActionPlanService;
use App\Controller\AuthController;

/** Conferma o annulla un piano preparato dal prompt (plan_actions). */
final class ActionPlansController extends AuthController
{
    public function executeAction(): void
    {
        $this->json($this->container->get(ActionPlanService::class)->execute((string) $this->param('id')));
    }

    public function cancelAction(): void
    {
        $this->container->get(ActionPlanService::class)->cancel((string) $this->param('id'));
        $this->json(['ok' => true, 'message' => 'Annullato: non ho fatto niente.']);
    }
}
