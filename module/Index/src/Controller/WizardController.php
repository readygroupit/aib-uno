<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\ShowWizardTool;

/**
 * Endpoint dedicato (non passa da /prompt): il modale si apre con un
 * click su un'icona sempre presente, non con un messaggio digitato - non
 * ha senso spendere un giro di interpretazione (locale o Claude) per
 * qualcosa che e' gia' un comando diretto e deterministico. Nessun
 * $requiredPermission qui: il filtro e' per singola capacita' dentro
 * ShowWizardTool, il modale in se' e' sempre apribile da chi e' loggato.
 */
final class WizardController extends AuthController
{
    public function indexAction(): void
    {
        $entity = (string) $this->param('entity', '');

        $components = $this->container->get(ShowWizardTool::class)->execute(['entity' => $entity]);

        $this->json(['components' => $components]);
    }
}
