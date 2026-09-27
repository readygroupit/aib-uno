<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\ShowHomeDashboardTool;

final class IndexController extends AuthController
{
    /**
     * Il prompt (l'ex componente 'hero') e' costruito interamente lato
     * client, sempre presente su ogni pagina (vedi hero.js/app.js). La
     * home pero' ora ha un contenuto suo (il cruscotto, vedi
     * ShowHomeDashboardTool): appena ci sono componenti, hero.js sposta
     * da solo il prompt in sidebar (stesso meccanismo gia' attivo su
     * ogni altra pagina, vedi hero.css .has-history) - nessuna scelta di
     * layout qui, solo popolare o meno initialComponents.
     */
    public function indexAction(): ?string
    {
        $components = $this->container->get(ShowHomeDashboardTool::class)->execute([]);

        return $this->renderPage($components, ['title' => 'Uno']);
    }

    public function helloAction(): string
    {
        return $this->render('index/hello', [
            'title' => 'Ciao',
            'name' => $this->param('name', 'sconosciuto'),
        ]);
    }
}
