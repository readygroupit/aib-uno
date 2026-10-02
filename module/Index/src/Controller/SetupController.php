<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Controller\AuthController;
use App\Prompt\Tool\ShowSetupTool;
use App\Service\SetupService;

/**
 * Configurazione: cosa manca per rendere operativo il sistema (vedi
 * SetupService). Le due azioni sui connettori rispondono sempre JSON,
 * chiamate da public/js/setup.js.
 */
final class SetupController extends AuthController
{
    protected ?string $requiredPermission = 'users.manage';

    public function indexAction(): ?string
    {
        $components = $this->container->get(ShowSetupTool::class)->execute([]);

        return $this->renderPage($components, ['title' => 'Configurazione']);
    }

    public function statusAction(): void
    {
        $this->json($this->container->get(SetupService::class)->payload());
    }

    public function connectAction(): void
    {
        $connector = $this->connectorOrFail();
        if ($connector === null) {
            return;
        }

        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        $input = is_array($body) ? array_map(static fn ($value) => is_string($value) ? $value : '', $body) : [];

        try {
            $message = $connector->connect($input);
        } catch (ConnectorException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);

            return;
        }

        $this->json(['ok' => true, 'message' => $message] + $this->container->get(SetupService::class)->payload());
    }

    public function disconnectAction(): void
    {
        $connector = $this->connectorOrFail();
        if ($connector === null) {
            return;
        }

        $connector->disconnect();

        $this->json(['ok' => true] + $this->container->get(SetupService::class)->payload());
    }

    private function connectorOrFail(): ?\App\Connector\ConnectorInterface
    {
        if ($this->request->getMethod() !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Metodo non consentito.'], 405);

            return null;
        }

        try {
            return $this->container->get(ConnectorRegistry::class)->get((string) $this->param('code'));
        } catch (\RuntimeException) {
            $this->json(['ok' => false, 'error' => 'Connettore sconosciuto.'], 404);

            return null;
        }
    }
}
