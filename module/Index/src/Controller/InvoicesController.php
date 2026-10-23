<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Invoicing\InvoiceService;
use App\Invoicing\InvoicingException;
use App\Prompt\Tool\EditInvoiceTool;
use App\Prompt\Tool\ListInvoicesTool;
use App\Repository\InvoiceRepository;

/**
 * Fatture. Oltre a crea/modifica: emetti (numero e totali), invia allo
 * SdI, scarica l'XML, pagata. Le azioni rispondono in JSON con il
 * messaggio; l'XML e' un download.
 */
final class InvoicesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return InvoiceRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditInvoiceTool::class;
    }

    protected function listToolClass(): string
    {
        return ListInvoicesTool::class;
    }

    protected function packageName(): string
    {
        return 'invoices';
    }

    protected function listPageTitle(): string
    {
        return 'Fatture';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova fattura';
    }

    protected function editPageTitle(): string
    {
        return 'Fattura';
    }

    protected function defaultsOnCreate(): array
    {
        $service = $this->container->get(InvoiceService::class);

        return [
            'stage' => 'bozza',
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days')),
            'tax_rate_percent' => (string) $service->defaultRate(),
            'payment_method' => 'bonifico',
            'sdi_status' => 'da inviare',
            'document_type' => 'fattura',
        ];
    }

    protected function createdMessage(): string
    {
        return 'Bozza creata: controlla e emetti quando e\' pronta.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'issue', 'send', 'paid', 'convert' => 'invoices.edit',
            'xml' => 'invoices.view',
            default => parent::requiredPermissionForAction($action),
        };
    }

    public function issueAction(): void
    {
        $this->respond(function (InvoiceService $service, int $id) {
            $invoice = $service->issue($id);
            $problems = $service->problems($invoice);

            return 'Emessa la fattura ' . $invoice->invoiceNumber . '.' . ($problems !== [] ? ' Per la fattura elettronica manca: ' . implode(', ', $problems) . '.' : '');
        });
    }

    public function sendAction(): void
    {
        $this->respond(static fn (InvoiceService $service, int $id) => $service->send($id));
    }

    public function paidAction(): void
    {
        $this->respond(static function (InvoiceService $service, int $id) {
            $service->markPaid($id);

            return 'Segnata come pagata.';
        });
    }

    public function convertAction(): void
    {
        try {
            $invoice = $this->container->get(InvoiceService::class)->convertProForma((int) $this->param('id'));
        } catch (InvoicingException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => 'Fattura pronta in bozza: controllala ed emettila.', 'open' => '/fatture/' . $invoice->id]);
    }

    public function xmlAction(): ?string
    {
        try {
            $built = $this->container->get(InvoiceService::class)->xml((int) $this->param('id'));
        } catch (InvoicingException $e) {
            $this->response->addHeader('Content-Type', 'text/plain; charset=utf-8');
            $this->response->setStatusCode(422);

            return $e->getMessage();
        }
        $this->response->addHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->response->addHeader('Content-Disposition', 'attachment; filename="' . $built['fileName'] . '"');

        return $built['xml'];
    }

    private function respond(callable $run): void
    {
        try {
            $message = $run($this->container->get(InvoiceService::class), (int) $this->param('id'));
            $this->json(['ok' => true, 'message' => $message]);
        } catch (InvoicingException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
