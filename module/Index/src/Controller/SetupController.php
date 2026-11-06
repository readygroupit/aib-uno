<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Connector\GmailConnector;
use App\Connector\GoogleCalendarConnector;
use App\Connector\GoogleDriveConnector;
use App\Connector\MetaConnector;
use App\Connector\PCloudConnector;
use App\Connector\RedirectingConnectorInterface;
use App\Controller\AuthController;
use App\Prompt\Tool\ShowSetupTool;
use App\Repository\CustomerRepository;
use App\Repository\SimulatedActionRepository;
use App\Repository\SocialAccountRepository;
use App\Service\BrandingService;
use App\Service\SetupService;
use App\TestMode\TestModeService;

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

    /** Ritorno da Facebook dopo il consenso (vedi MetaConnector). */
    public function metaCallbackAction(): ?string
    {
        $registry = $this->container->get(ConnectorRegistry::class);
        $connector = $registry->has('meta') ? $registry->get('meta') : null;
        $code = (string) $this->request->get('code', '');
        $flash = null;
        if ($code === '') {
            $flash = ['tone' => 'error', 'text' => 'Facebook e Instagram non collegati: accesso non autorizzato.'];
        } elseif ($connector instanceof MetaConnector) {
            try {
                $summary = $connector->completeAuthorization($code, (string) $this->request->get('state', ''));
                $flash = ['tone' => 'success', 'text' => "Facebook e Instagram collegati: {$summary}. Ora controlla a quale cliente va ognuno in \"Pagine e profili social\"."];
            } catch (ConnectorException $e) {
                $flash = ['tone' => 'error', 'text' => $e->getMessage()];
            }
        }

        return $this->backToSetup($flash);
    }

    /**
     * Pagine e profili social: a quale cliente va ognuno. GET la pagina;
     * POST 'account' + 'customer' (vuoto = nessuno) assegna, 'refresh' = 1
     * rilegge l'elenco da Meta.
     */
    public function socialAction(): ?string
    {
        $accounts = $this->container->get(SocialAccountRepository::class);
        if ($this->request->getMethod() === 'POST') {
            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
            try {
                if (!empty($body['refresh'])) {
                    $message = 'Elenco aggiornato: ' . $this->container->get(ConnectorRegistry::class)->get('meta')->refreshAccounts() . '.';
                } else {
                    $account = $accounts->find((int) ($body['account'] ?? 0));
                    if ($account === null) {
                        throw new ConnectorException('Pagina o profilo non trovato.');
                    }
                    $customer = (int) ($body['customer'] ?? 0);
                    $accounts->update((int) $account->id, ['customer_id' => $customer > 0 ? $customer : null]);
                    $message = $customer > 0 ? 'Assegnato.' : 'Tolto dal cliente.';
                }
            } catch (ConnectorException $e) {
                $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

                return null;
            }
            $this->json(['ok' => true, 'message' => $message, 'component' => $this->socialComponent()]);

            return null;
        }

        return $this->renderPage([$this->socialComponent()], ['title' => 'Pagine e profili social']);
    }

    private function socialComponent(): array
    {
        $customers = array_map(static fn ($c) => ['id' => (int) $c->id, 'name' => $c->displayName()], $this->container->get(CustomerRepository::class)->findAll());
        usort($customers, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        $rows = array_map(static fn ($a) => [
            'id' => (int) $a->id,
            'network' => (string) $a->network,
            'label' => $a->label(),
            'name' => (string) $a->name,
            'customerId' => $a->customerId,
        ], $this->container->get(SocialAccountRepository::class)->findAll([], 'network, name'));

        return [
            'type' => 'social-accounts',
            'title' => 'Pagine e profili social',
            'action' => '/configurazione/social',
            'intro' => 'Le pagine Facebook e i profili Instagram che l\'account collegato gestisce. Assegna ognuno al suo cliente: i contenuti di quel cliente escono li\'. Quelli con lo stesso nome di un cliente sono gia\' assegnati, controllali.',
            'accounts' => $rows,
            'customers' => $customers,
        ];
    }

    /** Modalita' prova (TestModeService): GET la pagina, POST salva. */
    public function testModeAction(): ?string
    {
        $test = $this->container->get(TestModeService::class);
        if ($this->request->getMethod() === 'POST') {
            $body = json_decode(file_get_contents('php://input') ?: '{}', true) ?: [];
            $error = $test->save($body);
            if ($error !== null) {
                $this->json(['ok' => false, 'message' => $error], 422);

                return null;
            }
            $this->json(['ok' => true, 'message' => $test->isEnabled() ? 'Salvato: modalita\' prova accesa.' : 'Salvato: modalita\' prova spenta, ora tutto e\' reale.', 'component' => $this->testModeComponent()]);

            return null;
        }

        return $this->renderPage([$this->testModeComponent()], ['title' => 'Modalita\' prova']);
    }

    private function testModeComponent(): array
    {
        $test = $this->container->get(TestModeService::class);
        $state = $test->state();
        $log = array_map(static fn ($a) => [
            'when' => date('d/m H:i', strtotime((string) $a->createdAt)),
            'effect' => TestModeService::EFFECTS[$a->effect] ?? (string) $a->effect,
            'summary' => (string) $a->summary,
            'detail' => $a->detail !== null ? mb_substr($a->detail, 0, 600) : null,
            'recipient' => $a->recipient,
        ], $this->container->get(SimulatedActionRepository::class)->findRecent(50));

        return [
            'type' => 'test-mode',
            'title' => 'Modalita\' prova',
            'action' => '/configurazione/prova',
            'enabled' => $state['enabled'],
            'email' => $state['email'],
            'phone' => $state['phone'],
            'effects' => array_map(static fn (string $key, string $label) => ['key' => $key, 'label' => $label, 'real' => in_array($key, $state['real'], true)], array_keys(TestModeService::EFFECTS), TestModeService::EFFECTS),
            'log' => $log,
        ];
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

    /**
     * Logo dell'azienda: la versione normale e quella per gli sfondi scuri
     * (vedi BrandingService). GET la pagina, POST carica un file
     * (campo 'file', tipo in 'kind') o lo toglie ('remove' = 1).
     */
    public function logoAction(): ?string
    {
        $branding = $this->container->get(BrandingService::class);
        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage([$this->logoComponent($branding)], ['title' => 'Logo dell\'azienda']);
        }

        $kind = (string) $this->request->get('kind', '');
        if (!isset(BrandingService::KINDS[$kind])) {
            $this->json(['ok' => false, 'error' => 'Logo sconosciuto.'], 422);

            return null;
        }
        if ($this->request->get('remove') === '1') {
            $branding->remove($kind);
        } else {
            $file = $this->request->file('file');
            $error = $file === null ? 'Scegli un\'immagine da caricare.' : $branding->store($kind, $file);
            if ($error !== null) {
                $this->json(['ok' => false, 'error' => $error], 422);

                return null;
            }
        }
        $this->json(['ok' => true, 'component' => $this->logoComponent($branding)]);

        return null;
    }

    private function logoComponent(BrandingService $branding): array
    {
        $current = $branding->current();

        return [
            'type' => 'brand-logos',
            'title' => 'Logo dell\'azienda',
            'action' => '/configurazione/logo',
            'logos' => [
                [
                    'kind' => 'logo',
                    'label' => 'Logo normale',
                    'help' => 'La versione per gli sfondi chiari. E\' lo stesso logo del primo accesso.',
                    'usedIn' => [
                        'Login: al centro, sopra "Bentornato" (senza, c\'e\' il logo di aiBrains)',
                        'Home: piccolo, sotto la casella "Cosa vuoi fare?"',
                        'Colonna della chat, in alto a sinistra: cliccandolo si torna alla home',
                        'Preventivo da stampare o salvare in PDF: in testa, sopra i dati dell\'azienda',
                    ],
                    'url' => $current['logo'],
                    'dark' => false,
                ],
                [
                    'kind' => 'logo_light',
                    'label' => 'Logo per sfondi scuri (di solito bianco)',
                    'help' => 'Facoltativo: la versione per gli sfondi scuri, di solito bianca.',
                    'usedIn' => [
                        'Area cliente: nel riquadro scuro in cima (senza, c\'e\' il nome dell\'agenzia)',
                    ],
                    'url' => $current['logoLight'],
                    'dark' => true,
                ],
            ],
            'note' => 'PNG, JPG o WebP, massimo 2 MB. Meglio un PNG con lo sfondo trasparente, largo almeno 400 pixel.',
        ];
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
