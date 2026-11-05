<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Connector\GmailConnector;
use App\Connector\GoogleCalendarConnector;
use App\Connector\GoogleDriveConnector;
use App\Connector\PCloudConnector;
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
     * l'utente ha rifiutato). Calendario, Drive e Gmail usano lo stesso
     * callback: lo "state" di Drive e Gmail comincia con il loro prefisso
     * (GoogleDriveConnector::STATE_PREFIX, GmailConnector::STATE_PREFIX).
     */
    public function googleCallbackAction(): ?string
    {
        $state = (string) $this->request->get('state', '');
        [$code, $label, $done] = match (true) {
            str_starts_with($state, GoogleDriveConnector::STATE_PREFIX) => ['google_drive', 'Google Drive', 'Foto e video si leggono e si descrivono da ora.'],
            str_starts_with($state, GmailConnector::STATE_PREFIX) => ['gmail', 'Gmail', 'Le email dei clienti arrivate da ora diventano richieste da smistare.'],
            default => ['google_calendar', 'Google Calendar', 'Gli appuntamenti si sincronizzano da ora.'],
        };
        $registry = $this->container->get(ConnectorRegistry::class);
        $connector = $registry->has($code) ? $registry->get($code) : null;
        $error = (string) $this->request->get('error', '');
        $flash = null;
        if ($error !== '') {
            $flash = ['tone' => 'error', 'text' => "{$label} non collegato: accesso non autorizzato su Google."];
        } elseif ($connector instanceof GoogleCalendarConnector || $connector instanceof GoogleDriveConnector || $connector instanceof GmailConnector) {
            try {
                $summary = $connector->completeAuthorization((string) $this->request->get('code', ''), $state);
                $flash = ['tone' => 'success', 'text' => "{$label} collegato: {$summary}. {$done}"];
            } catch (ConnectorException $e) {
                $flash = ['tone' => 'error', 'text' => $e->getMessage()];
            }
        }

        return $this->backToSetup($flash);
    }

    /** Ritorno da pCloud: codice, "state" e l'host del conto (USA o Europa). */
    public function pcloudCallbackAction(): ?string
    {
        $registry = $this->container->get(ConnectorRegistry::class);
        $connector = $registry->has('pcloud') ? $registry->get('pcloud') : null;
        $code = (string) $this->request->get('code', '');
        $flash = null;
        if ($code === '') {
            $flash = ['tone' => 'error', 'text' => 'pCloud non collegato: accesso non autorizzato.'];
        } elseif ($connector instanceof PCloudConnector) {
            try {
                $summary = $connector->completeAuthorization($code, (string) $this->request->get('state', ''), (string) $this->request->get('hostname', ''));
                $flash = ['tone' => 'success', 'text' => "pCloud collegato: {$summary}. Foto e video si leggono e si descrivono da ora."];
            } catch (ConnectorException $e) {
                $flash = ['tone' => 'error', 'text' => $e->getMessage()];
            }
        }

        return $this->backToSetup($flash);
    }

    /**
     * Esito in sessione e ritorno a /configurazione: ricaricando la
     * pagina non si ripresenta al servizio un codice gia' usato.
     */
    private function backToSetup(?array $flash): ?string
    {
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
