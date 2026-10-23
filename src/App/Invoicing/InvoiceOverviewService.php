<?php

declare(strict_types=1);

namespace App\Invoicing;

use App\Core\Container;
use App\Model\Installment;
use App\Model\Invoice;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\InstallmentRepository;
use App\Repository\InvoiceRepository;
use App\Support\ItalianFormat as F;

/**
 * Le fatture da mostrare, per cosa c'e' da fare: incassi ancora da
 * fatturare, bozze da emettere, emesse da mandare allo SdI, emesse da
 * incassare, pagate di recente. Piu' la scheda di una fattura.
 */
final class InvoiceOverviewService
{
    public const STAGE_TONES = ['bozza' => 'neutral', 'emessa' => 'info', 'pagata' => 'success', 'annullata' => 'neutral'];
    public const SDI_TONES = ['da inviare' => 'warning', 'inviata' => 'info', 'consegnata' => 'success', 'scartata' => 'danger', 'non consegnata' => 'warning'];

    private InvoiceRepository $invoices;
    private CustomerRepository $customers;
    private InvoiceService $service;
    private Container $container;
    private string $today;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->invoices = $container->get(InvoiceRepository::class);
        $this->customers = $container->get(CustomerRepository::class);
        $this->service = $container->get(InvoiceService::class);
        $this->today = date('Y-m-d');
    }

    public function board(): array
    {
        $names = [];
        foreach ($this->customers->findAll() as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }
        $all = $this->invoices->findAll([], 'issue_date DESC, id DESC');
        $year = substr($this->today, 0, 4);
        $hasProvider = $this->service->provider() !== null;

        $drafts = array_filter($all, static fn (Invoice $i) => $i->stage === 'bozza');
        $toSend = array_filter($all, static fn (Invoice $i) => !$i->isProForma() && $i->stage !== 'bozza' && $i->stage !== 'annullata' && in_array($i->sdiStatus, ['da inviare', 'scartata', null], true));
        $unpaid = array_filter($all, static fn (Invoice $i) => $i->stage === 'emessa');
        $paid = array_slice(array_values(array_filter($all, static fn (Invoice $i) => $i->stage === 'pagata')), 0, 6);
        $issuedYear = array_filter($all, static fn (Invoice $i) => !$i->isProForma() && in_array($i->stage, ['emessa', 'pagata'], true) && substr((string) $i->issueDate, 0, 4) === $year);
        $overdue = array_filter($unpaid, fn (Invoice $i) => $i->dueDate !== null && $i->dueDate < $this->today);

        $toInvoice = [];
        if (in_array('installments', PackageCatalogService::installedNames(), true)) {
            $since = date('Y-m-d', strtotime($this->today . ' -60 days'));
            foreach ($this->container->get(InstallmentRepository::class)->findWithoutInvoice(date('Y-m-d', strtotime($this->today . ' +10 days')), $since) as $installment) {
                $toInvoice[] = $this->installmentItem($installment, $names);
            }
        }

        $item = fn (Invoice $i) => $this->item($i, $names, $hasProvider);

        return [
            'totals' => [
                'issuedYear' => array_sum(array_map(static fn (Invoice $i) => (float) $i->total, $issuedYear)),
                'issuedYearCount' => count($issuedYear),
                'unpaid' => array_sum(array_map(static fn (Invoice $i) => (float) $i->total, $unpaid)),
                'unpaidCount' => count($unpaid),
                'overdueCount' => count($overdue),
                'toSendCount' => count($toSend),
                'draftsCount' => count($drafts),
                'toInvoiceCount' => count($toInvoice),
            ],
            'hasProvider' => $hasProvider,
            'groups' => array_values(array_filter([
                ['key' => 'to-invoice', 'title' => 'Da fatturare', 'hint' => 'Incassi in scadenza o gia\' incassati, ancora senza fattura.', 'tone' => 'warning', 'items' => $toInvoice],
                ['key' => 'drafts', 'title' => 'Bozze da emettere', 'hint' => 'Controlla e emetti: il numero si assegna da solo.', 'tone' => 'neutral', 'items' => array_values(array_map($item, $drafts))],
                ['key' => 'to-send', 'title' => 'Da inviare allo SdI', 'hint' => $hasProvider ? 'Emesse ma non ancora partite.' : 'Scarica l\'XML e caricalo sul portale, oppure collega Fatture in Cloud o Aruba.', 'tone' => 'warning', 'items' => array_values(array_map($item, $toSend))],
                ['key' => 'unpaid', 'title' => 'Da incassare', 'hint' => null, 'tone' => count($overdue) > 0 ? 'danger' : 'info', 'items' => array_values(array_map($item, $unpaid))],
            ], static fn (array $g) => $g['items'] !== [])),
            'recentPaid' => array_map($item, $paid),
        ];
    }

    /** Scheda: dati, cosa manca per l'XML, le azioni che hanno senso adesso. */
    public function detail(Invoice $invoice): array
    {
        $customer = $this->customers->find((int) $invoice->customerId);
        $provider = $this->service->provider();
        $problems = $this->service->problems($invoice);
        // Il numero lo assegna l'emissione: in bozza non e' un problema.
        if ($invoice->stage === 'bozza') {
            $problems = array_values(array_filter($problems, static fn (string $p) => !str_starts_with($p, 'numero')));
        }
        // La pro forma non e' una fattura elettronica.
        if ($invoice->isProForma()) {
            $problems = [];
        }
        $base = '/fatture/' . $invoice->id;
        $actions = [];
        if ($invoice->stage === 'bozza') {
            $actions[] = ['label' => 'Emetti', 'action' => "{$base}/emetti", 'variant' => 'primary', 'icon' => 'check', 'confirm' => 'Emettere la fattura? Prende il prossimo numero dell\'anno e non si potra\' piu\' cancellare, solo annullare.'];
        }
        if ($invoice->isProForma() && $invoice->stage === 'emessa') {
            $actions[] = ['label' => 'Trasforma in fattura', 'action' => "{$base}/in-fattura", 'variant' => 'primary', 'icon' => 'file-invoice', 'confirm' => 'Creare la fattura da questa pro forma? La pro forma resta in archivio come annullata.'];
        }
        if (!$invoice->isProForma() && in_array($invoice->stage, ['emessa', 'pagata'], true)) {
            if ($provider !== null && !in_array($invoice->sdiStatus, ['inviata', 'consegnata'], true)) {
                $actions[] = ['label' => 'Invia allo SdI', 'action' => "{$base}/invia", 'variant' => 'primary', 'icon' => 'paper-plane', 'confirm' => 'Mandare la fattura allo SdI con ' . $provider->label() . '?'];
            }
            $actions[] = ['label' => 'Scarica XML', 'href' => "{$base}/xml", 'variant' => 'secondary', 'icon' => 'download', 'download' => true];
        }
        if ($invoice->stage === 'emessa') {
            $actions[] = ['label' => 'Pagata', 'action' => "{$base}/pagata", 'variant' => 'secondary', 'icon' => 'circle-check'];
        }

        $subtotal = (float) $invoice->subtotal;
        $rate = $invoice->taxRatePercent !== null ? (float) $invoice->taxRatePercent : $this->service->defaultRate();
        $tax = $invoice->taxAmount !== null ? (float) $invoice->taxAmount : round($subtotal * $rate / 100, 2);

        return [
            'number' => $invoice->invoiceNumber,
            'title' => ($invoice->isProForma() ? 'Pro forma' : 'Fattura') . ($invoice->invoiceNumber !== null ? ' ' . $invoice->invoiceNumber : ' (bozza)'),
            'stage' => (string) $invoice->stage,
            'stageTone' => self::STAGE_TONES[$invoice->stage] ?? 'neutral',
            'sdi' => $invoice->stage !== 'bozza' && !$invoice->isProForma() ? $this->sdiLabel($invoice) : ($invoice->isProForma() ? 'Pro forma: senza valore fiscale' : null),
            'sdiTone' => $invoice->isProForma() ? 'neutral' : (self::SDI_TONES[$invoice->sdiStatus] ?? 'neutral'),
            'customer' => $customer?->displayName(),
            'customerHref' => $customer !== null ? '/clienti/' . $customer->id : null,
            'description' => $invoice->description,
            'date' => $invoice->issueDate !== null ? F::longDate($invoice->issueDate) : null,
            'due' => $invoice->dueDate !== null ? F::longDate($invoice->dueDate) . ($invoice->stage === 'emessa' ? ' (' . F::relativeDays($invoice->dueDate, $this->today) . ')' : '') : null,
            'method' => $invoice->paymentMethod,
            'lines' => [
                ['label' => 'Imponibile', 'value' => F::euro($subtotal)],
                ['label' => $rate > 0 ? 'IVA ' . rtrim(rtrim(number_format($rate, 2, ',', ''), '0'), ',') . '%' : 'IVA (non applicata)', 'value' => F::euro($tax)],
                ['label' => 'Totale', 'value' => F::euro($invoice->total !== null ? (float) $invoice->total : $subtotal + $tax), 'strong' => true],
            ],
            'problems' => $problems,
            'installmentHref' => $invoice->installmentId !== null ? '/scadenzario/' . $invoice->installmentId : null,
            'actions' => $actions,
        ];
    }

    private function item(Invoice $invoice, array $names, bool $hasProvider): array
    {
        $isLate = $invoice->stage === 'emessa' && $invoice->dueDate !== null && $invoice->dueDate < $this->today;
        $base = '/fatture/' . $invoice->id;
        $action = match (true) {
            $invoice->stage === 'bozza' => ['label' => 'Emetti', 'action' => "{$base}/emetti", 'icon' => 'check'],
            $invoice->isProForma() && $invoice->stage === 'emessa' => ['label' => 'In fattura', 'action' => "{$base}/in-fattura", 'icon' => 'file-invoice'],
            $hasProvider && !$invoice->isProForma() && in_array($invoice->sdiStatus, ['da inviare', 'scartata', null], true) && $invoice->stage !== 'annullata' => ['label' => 'Invia allo SdI', 'action' => "{$base}/invia", 'icon' => 'paper-plane'],
            $invoice->stage === 'emessa' => ['label' => 'Pagata', 'action' => "{$base}/pagata", 'icon' => 'check'],
            default => null,
        };

        return [
            'id' => (int) $invoice->id,
            'href' => $base,
            'number' => $invoice->invoiceNumber ?? 'bozza',
            'customer' => $names[(int) $invoice->customerId] ?? '',
            'customerHref' => '/clienti/' . $invoice->customerId,
            'description' => (string) ($invoice->description ?: 'Fattura'),
            'date' => F::shortDate($invoice->stage === 'emessa' && $invoice->dueDate ? $invoice->dueDate : $invoice->issueDate, $this->today),
            'when' => match (true) {
                $isLate => 'scaduta da ' . F::plural(-F::daysFrom((string) $invoice->dueDate, $this->today), 'giorno', 'giorni'),
                $invoice->stage === 'emessa' && $invoice->dueDate !== null => 'scade ' . F::relativeDays($invoice->dueDate, $this->today),
                $invoice->stage === 'pagata' => 'pagata',
                default => 'del ' . F::shortDate($invoice->issueDate, $this->today),
            },
            'amount' => F::euro((float) ($invoice->total ?? $invoice->subtotal)),
            'tone' => $isLate ? 'danger' : (self::STAGE_TONES[$invoice->stage] ?? 'neutral'),
            'sdi' => $invoice->isProForma() ? 'pro forma' : ($invoice->stage !== 'bozza' ? $this->sdiLabel($invoice) : null),
            'sdiTone' => $invoice->isProForma() ? 'neutral' : (self::SDI_TONES[$invoice->sdiStatus] ?? 'neutral'),
            'action' => $action,
        ];
    }

    private function installmentItem(Installment $installment, array $names): array
    {
        $isPaid = $installment->stage === 'incassato';

        return [
            'id' => (int) $installment->id,
            'href' => '/scadenzario/' . $installment->id,
            'number' => null,
            'customer' => $names[(int) $installment->customerId] ?? '',
            'customerHref' => '/clienti/' . $installment->customerId,
            'description' => (string) $installment->description,
            'date' => F::shortDate($isPaid ? $installment->paidAt : $installment->dueDate, $this->today),
            'when' => $isPaid ? 'incassato, senza fattura' : 'scade ' . F::relativeDays((string) $installment->dueDate, $this->today),
            'amount' => F::euro((float) $installment->amount) . ' + IVA',
            'tone' => $isPaid ? 'warning' : 'neutral',
            'sdi' => null,
            'sdiTone' => 'neutral',
            'action' => ['label' => 'Prepara fattura', 'action' => '/scadenzario/' . $installment->id . '/fattura', 'icon' => 'file-invoice'],
            // Chi prima chiede il pagamento e poi fattura: la pro forma.
            'secondAction' => $isPaid ? null : ['label' => 'Pro forma', 'action' => '/scadenzario/' . $installment->id . '/fattura?tipo=pro%20forma', 'icon' => 'file-invoice'],
        ];
    }

    private function sdiLabel(Invoice $invoice): string
    {
        return match ($invoice->sdiStatus) {
            'inviata' => 'SdI: inviata',
            'consegnata' => 'SdI: consegnata',
            'scartata' => 'SdI: scartata',
            'non consegnata' => 'SdI: non consegnata',
            default => 'SdI: da inviare',
        };
    }
}
