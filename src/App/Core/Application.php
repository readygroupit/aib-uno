<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\AbstractController;
use App\Service\LoggerService;

final class Application
{
    private readonly Router $router;

    public function __construct(private readonly Container $container)
    {
        $this->router = new Router(require CONFIG_PATH . '/routes.php');
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $response = new Response();

        try {
            $match = $this->router->match($request->getPathInfo());
            if ($match === null) {
                throw new Exception\NotFoundException("Nessuna route per {$request->getPathInfo()}");
            }

            $controller = $this->container->get($match['controller']);
            if (!$controller instanceof AbstractController) {
                throw new Exception\HttpException("{$match['controller']} deve estendere AbstractController");
            }

            $this->container->get(LoggerService::class)->setRequestContext($match['controller'], $match['action']);

            /**
             * Unico punto di controllo CSRF per tutto il framework: ogni
             * POST deve portare il token generato da Csrf::token() - come
             * campo '_csrf' per i form (vedi View::csrfField() e
             * FormComponent::toData()), o come header X-CSRF-Token per le
             * richieste con corpo JSON come /prompt (vedi window.UNO_CSRF
             * in layout.phtml e Request::csrfHeader()). Cosi' non va
             * ricordato form per form/endpoint per endpoint.
             */
            $submittedToken = $request->get('_csrf') ?? $request->csrfHeader();
            if ($request->getMethod() === 'POST' && !Csrf::verify($submittedToken)) {
                throw new Exception\ForbiddenException('Token CSRF non valido o mancante.');
            }

            $controller->setRequest($request);
            $controller->setResponse($response);
            $controller->setRouteParams($match['params']);
            $controller->setAction($match['action']);
            $controller->init();

            if (!$response->isFinished()) {
                $action = $match['action'] . 'Action';
                if (!method_exists($controller, $action)) {
                    throw new Exception\NotFoundException("Action $action non trovata su {$match['controller']}");
                }

                $result = $controller->$action();
                if (is_string($result)) {
                    $response->setBody($result);
                }
            }
        } catch (Exception\NotFoundException $e) {
            $response->setStatusCode(404);
            $response->setBody($this->renderError('404', $e));
        } catch (Exception\ForbiddenException $e) {
            $response->setStatusCode(403);
            $response->setBody($this->renderError('403', $e));
        } catch (\Throwable $e) {
            $this->container->get(LoggerService::class)->logError($e);
            $response->setStatusCode(500);
            $response->setBody($this->renderError('500', $e));
        }

        $response->send();
    }

    private function renderError(string $code, \Throwable $e): string
    {
        $view = new View();

        return $view->renderWithLayout(
            ROOT_PATH . "/view/error/{$code}.phtml",
            ['title' => "Errore $code", 'exception' => $e],
            ROOT_PATH . '/view/layout/layout.phtml'
        );
    }
}
