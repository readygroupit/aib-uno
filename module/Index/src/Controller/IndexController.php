<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;

final class IndexController extends AuthController
{
    /**
     * Il prompt (l'ex componente 'hero') e' ormai costruito interamente
     * lato client, sempre presente su ogni pagina (vedi hero.js/app.js) -
     * la home non ha piu' un contenuto server suo, e' solo lo stato
     * "vuoto" di quello stesso prompt persistente.
     */
    public function indexAction(): ?string
    {
        return $this->renderPage([], ['title' => 'Uno']);
    }

    public function helloAction(): string
    {
        return $this->render('index/hello', [
            'title' => 'Ciao',
            'name' => $this->param('name', 'sconosciuto'),
        ]);
    }
}
