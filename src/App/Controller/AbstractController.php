<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Container;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

abstract class AbstractController
{
    protected Request $request;
    protected Response $response;
    protected array $routeParams = [];
    protected string $action = '';

    public function __construct(protected readonly Container $container)
    {
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * Nome azione (es. 'edit', senza il suffisso 'Action') impostato da
     * Application::run() PRIMA di init() - serve ad AuthController per
     * poter chiedere un permesso diverso per azione diversa sulla stessa
     * classe (vedi requiredPermissionForAction()), invece del singolo
     * $requiredPermission valido per l'intero controller.
     */
    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    /**
     * Hook eseguito prima dell'action. Le sottoclassi (es. AuthController)
     * possono interromperlo chiamando redirect()/json(): l'Application
     * controlla response->isFinished() e non chiama piu' l'action.
     */
    public function init(): void
    {
    }

    protected function param(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $this->request->get($name, $default);
    }

    protected function render(string $template, array $vars = [], bool $useLayout = true): string
    {
        $view = new View();
        $templateFile = $this->viewBasePath() . $template . '.phtml';

        if (!$useLayout) {
            return $view->render($templateFile, $vars);
        }

        return $view->renderWithLayout($templateFile, $vars, ROOT_PATH . '/view/layout/layout.phtml');
    }

    /**
     * Pagina fatta di componenti: ogni voce di $componentsData e' un array
     * (con 'type') prodotto da AbstractComponent::toData(), mai HTML. Il
     * DOM lo costruisce sempre lo stesso JS, in due casi diversi:
     *  - richiesta AJAX (isAjax()): risponde con JSON puro, nessun HTML;
     *  - caricamento diretto della pagina: risponde con la shell (layout +
     *    un contenitore vuoto) e il JSON incorporato in uno <script>, che
     *    lo stesso bootstrap JS legge e disegna al caricamento.
     * Cosi' non esiste un secondo motore di rendering lato server.
     */
    protected function renderPage(array $componentsData, array $vars = []): ?string
    {
        if ($this->request->isAjax()) {
            $this->json(['components' => $componentsData]);

            return null;
        }

        $json = json_encode(
            ['components' => $componentsData],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

        $view = new View();

        return $view->renderWithLayout(
            ROOT_PATH . '/view/layout/page-root.phtml',
            $vars + ['json' => $json],
            ROOT_PATH . '/view/layout/layout.phtml'
        );
    }

    protected function redirect(string $url, int $status = 302): void
    {
        $this->response->redirect($url, $status);
    }

    protected function json(array $data, int $status = 200): void
    {
        $this->response->json($data, $status);
    }

    /**
     * module/<NomeModulo>/view/ dedotto dal namespace del controller
     * (es. Index\Controller\IndexController -> module/Index/view/).
     */
    private function viewBasePath(): string
    {
        $moduleName = strstr(static::class, '\\', true) ?: static::class;

        return ROOT_PATH . "/module/{$moduleName}/view/";
    }
}
