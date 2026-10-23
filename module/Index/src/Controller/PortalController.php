<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorException;
use App\Controller\AbstractController;
use App\Model\Customer;
use App\Payments\PaymentService;
use App\Portal\PortalService;

/**
 * /area/:token, l'area del cliente (vedi PortalService): pagina pubblica,
 * il link e' la chiave. Ogni POST torna alla pagina con l'esito in cima
 * (POST-redirect-GET: un aggiorna non ripete l'azione).
 */
final class PortalController extends AbstractController
{
    private const FLASH = 'portal_flash';

    public function indexAction(): string
    {
        $customer = $this->customer();
        if ($customer === null) {
            return $this->missing();
        }

        $flash = $_SESSION[self::FLASH] ?? null;
        unset($_SESSION[self::FLASH]);
        // Ritorno da Stripe/PayPal: l'esito vero arriva dal fornitore, qui solo il messaggio.
        $flash ??= match ((string) $this->request->get('pagamento', '')) {
            'ok' => ['tone' => 'success', 'text' => 'Pagamento ricevuto, grazie! Tra pochi istanti risulta qui sotto.'],
            'annullato' => ['tone' => 'info', 'text' => 'Pagamento annullato: puoi riprovare quando vuoi.'],
            'errore' => ['tone' => 'error', 'text' => 'Il pagamento non e\' andato a buon fine. Riprova o scrivici.'],
            default => match ((string) $this->request->get('automatico', '')) {
                'ok' => ['tone' => 'success', 'text' => 'Fatto: le prossime scadenze si addebitano da sole. Ti avvisiamo prima di ogni addebito.'],
                'annullato' => ['tone' => 'info', 'text' => 'Addebito automatico non attivato.'],
                default => null,
            },
        };

        return $this->render('portal/index', [
            'title' => 'La tua area',
            'token' => (string) $this->param('token'),
            'flash' => $flash,
            'view' => $this->container->get(PortalService::class)->view($customer),
        ], false);
    }

    public function approveAction(): void
    {
        $this->act(fn (PortalService $portal, Customer $c) => $portal->approve($c, (int) $this->request->get('post')));
    }

    public function acceptQuoteAction(): void
    {
        $this->act(fn (PortalService $portal, Customer $c) => $portal->acceptQuote($c, (int) $this->request->get('quote')));
    }

    public function approveAllAction(): void
    {
        $this->act(static fn (PortalService $portal, Customer $c) => $portal->approveAll($c));
    }

    public function changesAction(): void
    {
        $this->act(fn (PortalService $portal, Customer $c) => $portal->requestChanges($c, (int) $this->request->get('post'), (string) $this->request->get('comment', '')));
    }

    public function methodAction(): void
    {
        $this->act(fn (PortalService $portal, Customer $c) => $portal->setPaymentMethod($c, (string) $this->request->get('method', '')));
    }

    public function autoPayOffAction(): void
    {
        $this->act(static fn (PortalService $portal, Customer $c) => $portal->disableAutoPay($c));
    }

    /** "Paga ora": si crea il link e si va diretti alla pagina del fornitore. */
    public function payAction(): void
    {
        $customer = $this->customer();
        if ($customer === null || $this->request->getMethod() !== 'POST') {
            $this->redirect('/area/' . rawurlencode((string) $this->param('token')));

            return;
        }
        $portal = $this->container->get(PortalService::class);
        $installment = $portal->ownOpenInstallment($customer, (int) $this->request->get('installment'));
        $back = '/area/' . $customer->portalToken;
        if ($installment === null) {
            $this->back(['tone' => 'info', 'text' => 'Questo pagamento risulta gia\' fatto.']);

            return;
        }
        try {
            $link = $this->container->get(PaymentService::class)->createLink((int) $installment->id, $this->request->get('provider') ?: null, $this->request->baseUrl(), $back);
            $this->redirect($link['url']);
        } catch (ConnectorException $e) {
            $this->back(['tone' => 'error', 'text' => 'Il pagamento online non e\' disponibile in questo momento: ' . $e->getMessage()]);
        }
    }

    /** Pagina Stripe in cui il cliente salva carta o conto per gli addebiti automatici. */
    public function autoPayAction(): void
    {
        $customer = $this->customer();
        if ($customer === null || $this->request->getMethod() !== 'POST') {
            $this->redirect('/area/' . rawurlencode((string) $this->param('token')));

            return;
        }
        try {
            $url = $this->container->get(PaymentService::class)->autoPaySetupLink((int) $customer->id, $this->request->baseUrl(), '/area/' . $customer->portalToken);
            $this->redirect($url);
        } catch (ConnectorException $e) {
            $this->back(['tone' => 'error', 'text' => $e->getMessage()]);
        }
    }

    private function act(callable $run): void
    {
        $customer = $this->customer();
        if ($customer === null || $this->request->getMethod() !== 'POST') {
            $this->redirect('/area/' . rawurlencode((string) $this->param('token')));

            return;
        }
        try {
            $this->back(['tone' => 'success', 'text' => $run($this->container->get(PortalService::class), $customer)]);
        } catch (\InvalidArgumentException $e) {
            $this->back(['tone' => 'error', 'text' => $e->getMessage()]);
        }
    }

    private function back(array $flash): void
    {
        $_SESSION[self::FLASH] = $flash;
        $this->redirect('/area/' . rawurlencode((string) $this->param('token')));
    }

    private function customer(): ?Customer
    {
        return $this->container->get(PortalService::class)->customerByToken((string) $this->param('token'));
    }

    private function missing(): string
    {
        $this->response->setStatusCode(404);

        return $this->render('portal/missing', ['title' => 'Link non valido'], false);
    }
}
