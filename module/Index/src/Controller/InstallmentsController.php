<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Billing\PaymentPlanService;
use App\Connector\ConnectorException;
use App\Controller\AbstractEntityController;
use App\Invoicing\InvoiceService;
use App\Invoicing\InvoicingException;
use App\Payments\PaymentService;
use App\Repository\CustomerRepository;
use App\Model\Installment;
use App\Prompt\Tool\EditInstallmentTool;
use App\Prompt\Tool\ShowDueScheduleTool;
use App\Repository\InstallmentRepository;
use App\Support\ItalianFormat as F;

/**
 * Scadenzario. Oltre al solito crea/modifica: "incassato" e "sollecitato"
 * con un clic dalle liste (scadenzario, scheda cliente, servizio), che
 * rispondono in JSON con il messaggio da mostrare; la schermata la
 * ricarica il browser.
 */
final class InstallmentsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return InstallmentRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditInstallmentTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowDueScheduleTool::class;
    }

    protected function packageName(): string
    {
        return 'installments';
    }

    protected function listPageTitle(): string
    {
        return 'Scadenzario';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo incasso';
    }

    protected function editPageTitle(): string
    {
        return 'Incasso';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'da incassare', 'kind' => 'unica soluzione', 'due_date' => date('Y-m-d'), 'payment_method' => 'bonifico'];
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'pay', 'remind', 'link' => 'installments.edit',
            'invoice' => 'invoices.create',
            default => parent::requiredPermissionForAction($action),
        };
    }

    public function payAction(): void
    {
        $installment = $this->repository()->find((int) $this->param('id'));
        if (!$installment instanceof Installment || $installment->stage !== 'da incassare') {
            $this->json(['ok' => false, 'message' => 'Questo incasso non e\' piu\' da incassare.'], 409);

            return;
        }

        $this->container->get(PaymentPlanService::class)->markPaid((int) $installment->id);
        $this->json(['ok' => true, 'message' => 'Segnato come incassato: ' . F::euro((float) $installment->amount) . '.']);
    }

    /** Ricorda solo che il cliente e' stato sollecitato oggi: il messaggio vero lo manda ancora l'operatore. */
    public function remindAction(): void
    {
        $installment = $this->repository()->find((int) $this->param('id'));
        if (!$installment instanceof Installment) {
            $this->json(['ok' => false, 'message' => 'Incasso non trovato.'], 404);

            return;
        }

        $this->repository()->update((int) $installment->id, ['reminder_sent_at' => date('Y-m-d H:i:s')]);
        $this->json(['ok' => true, 'message' => 'Segnato come sollecitato oggi.']);
    }

    /** La fattura di questo incasso (la crea in bozza se non c'e'), poi la si apre. */
    public function invoiceAction(): void
    {
        try {
            $type = $this->request->get('tipo') === 'pro forma' ? 'pro forma' : 'fattura';
            $invoice = $this->container->get(InvoiceService::class)->createFromInstallment((int) $this->param('id'), $type);
        } catch (InvoicingException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => $invoice->isProForma() ? 'Bozza di pro forma pronta.' : 'Bozza di fattura pronta.', 'open' => '/fatture/' . $invoice->id]);
    }

    /**
     * Link per pagare online (Stripe o PayPal): torna il link e un testo
     * pronto da mandare al cliente, la schermata offre copia e email.
     */
    public function linkAction(): void
    {
        $payments = $this->container->get(PaymentService::class);
        try {
            $link = $payments->createLink((int) $this->param('id'), $this->request->get('fornitore') ?: null, $this->request->baseUrl());
        } catch (ConnectorException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $installment = $this->repository()->find((int) $this->param('id'));
        $customer = $this->container->get(CustomerRepository::class)->find((int) $installment->customerId);
        $amount = F::euro($payments->grossAmount($installment));
        $text = "Buongiorno,\nper il pagamento di \u{ab}{$installment->description}\u{bb} ({$amount}) potete usare questo link sicuro:\n{$link['url']}\n\nGrazie!";

        $this->json([
            'ok' => true,
            'share' => [
                'title' => 'Link di pagamento',
                'intro' => "{$amount} per {$installment->description}. Il pagamento risulta incassato da solo quando il cliente paga.",
                'url' => $link['url'],
                'email' => $customer?->email,
                'subject' => 'Pagamento: ' . $installment->description,
                'text' => $text,
            ],
        ]);
    }
}
