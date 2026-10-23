<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Connector\GoogleCalendarConnector;
use App\Connector\GoogleDriveConnector;
use App\Connector\RedirectingConnectorInterface;
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
        $flash = $_SESSION['setup_flash'] ?? null;
        unset($_SESSION['setup_flash']);
        if (is_array($flash)) {
            array_unshift($components, ['type' => 'notice'] + $flash);
        }

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

        // OAuth: il collegamento si completa su Google, il browser ci va.
        $redirect = $connector instanceof RedirectingConnectorInterface ? $connector->redirectUrl() : null;
        $this->json(['ok' => true, 'message' => $message, 'redirect' => $redirect] + $this->container->get(SetupService::class)->payload());
    }

    /**
     * Ritorno da Google con il codice di autorizzazione (o l'errore se
     * l'utente ha rifiutato). Calendario e Drive usano lo stesso callback:
     * lo "state" del Drive comincia con GoogleDriveConnector::STATE_PREFIX.
     */
    public function googleCallbackAction(): ?string
    {
        $state = (string) $this->request->get('state', '');
        $isDrive = str_starts_with($state, GoogleDriveConnector::STATE_PREFIX);
        $registry = $this->container->get(ConnectorRegistry::class);
        $connector = $registry->has($isDrive ? 'google_drive' : 'google_calendar') ? $registry->get($isDrive ? 'google_drive' : 'google_calendar') : null;
        $label = $isDrive ? 'Google Drive' : 'Google Calendar';
        $error = (string) $this->request->get('error', '');
        $flash = null;
        if ($error !== '') {
            $flash = ['tone' => 'error', 'text' => "{$label} non collegato: accesso non autorizzato su Google."];
        } elseif ($connector instanceof GoogleCalendarConnector || $connector instanceof GoogleDriveConnector) {
            try {
                $summary = $connector->completeAuthorization((string) $this->request->get('code', ''), $state);
                $flash = ['tone' => 'success', 'text' => "{$label} collegato: {$summary}. " . ($isDrive ? 'Foto e video si leggono e si descrivono da ora.' : 'Gli appuntamenti si sincronizzano da ora.')];
            } catch (ConnectorException $e) {
                $flash = ['tone' => 'error', 'text' => $e->getMessage()];
            }
        }

        // Esito in sessione e ritorno a /configurazione: ricaricando la
        // pagina non si ripresenta a Google un codice gia' usato.
        $_SESSION['setup_flash'] = $flash;
        $this->redirect('/configurazione');

        return null;
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
