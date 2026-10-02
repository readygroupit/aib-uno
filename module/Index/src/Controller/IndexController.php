<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\PromptToolRegistry;

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
        // La home e' quella del progetto: il cruscotto di un gestionale
        // (se ne ha i pacchetti), i Progetti su Uno, il menu altrimenti.
        $registry = $this->container->get(PromptToolRegistry::class);
        $home = match (true) {
            $registry->has('show_home_dashboard') => 'show_home_dashboard',
            $registry->has('show_projects') => 'show_projects',
            default => 'show_menu',
        };
        $components = $registry->get($home)->execute(['range' => (string) $this->param('range', 'today')]);

        return $this->renderPage($components, ['title' => APP_NAME]);
    }

    public function helloAction(): string
    {
        return $this->render('index/hello', [
            'title' => 'Ciao',
            'name' => $this->param('name', 'sconosciuto'),
        ]);
    }
}
