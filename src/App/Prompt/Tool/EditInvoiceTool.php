<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Invoicing\InvoiceOverviewService;
use App\Model\AbstractModel;
use App\Model\Invoice;
use App\Repository\InvoiceRepository;
use App\View\Component\InvoiceSummaryComponent;

final class EditInvoiceTool extends AbstractEditEntityTool
{
    private InvoiceOverviewService $overview;
    private InvoiceSummaryComponent $summary;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->overview = $container->get(InvoiceOverviewService::class);
        $this->summary = $container->get(InvoiceSummaryComponent::class);
    }

    protected function repositoryClass(): string
    {
        return InvoiceRepository::class;
    }

    protected function packageName(): string
    {
        return 'invoices';
    }

    protected function entityTag(): string
    {
        return 'invoice';
    }

    protected function baseUrl(): string
    {
        return '/fatture';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova fattura' : 'Modifica fattura';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Dati della fattura';
    }

    /** Numero, importi calcolati e SdI li scrivono emissione e invio, non il form. */
    protected function systemManagedFields(): array
    {
        return ['invoice_number', 'number_year', 'number_seq', 'tax_amount', 'total', 'sdi_status', 'sdi_provider', 'sdi_reference', 'sent_at', 'installment_id', 'customer_service_id'];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof Invoice) {
            return [];
        }

        return [$this->summary->toData(['url' => '/fatture/' . $model->id, 'invoice' => $this->overview->detail($model)])];
    }

    protected function searchColumns(): array
    {
        return ['invoice_number', 'description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'invoiceNumber', 'label' => 'Numero'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    public function name(): string
    {
        return 'edit_invoice';
    }

    public function description(): string
    {
        return "Apre una fattura (cercata per id, numero o descrizione) per emetterla, inviarla allo SdI, scaricarne l'XML o segnarla pagata.";
    }

    public function requiredPermission(): ?string
    {
        return 'invoices.edit';
    }
}
