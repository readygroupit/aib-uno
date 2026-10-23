<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Core\Container;
use App\Model\Quote;
use App\Model\QuoteLine;
use App\Repository\CustomerRepository;
use App\Repository\QuoteLineRepository;
use App\Repository\QuoteRepository;
use App\Repository\UserRepository;
use App\Support\ItalianFormat as F;

/** I preventivi da mostrare: per cosa c'e' da fare, e la scheda con le righe. */
final class QuoteOverviewService
{
    public const STAGE_TONES = ['bozza' => 'neutral', 'da approvare' => 'warning', 'approvato' => 'info', 'inviato' => 'accent', 'accettato' => 'success', 'rifiutato' => 'danger', 'scaduto' => 'neutral'];
    private const PERIOD_LABELS = ['mensile' => 'al mese', 'trimestrale' => 'a trimestre', 'semestrale' => 'a semestre', 'annuale' => "all'anno"];

    private Container $container;
    private string $today;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->today = date('Y-m-d');
    }

    public function board(bool $canApprove): array
    {
        $this->container->get(QuoteService::class)->expireOld();
        $quotes = $this->container->get(QuoteRepository::class)->findAll([], 'quote_date DESC, id DESC');
        $names = [];
        foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
            $names[(int) $customer->id] = $customer->displayName();
        }
        $row = fn (Quote $q, array $actions = []) => [
            'href' => '/preventivi/' . $q->id,
            'title' => ($names[(int) $q->customerId] ?? '') . ' · ' . $q->title,
            'meta' => implode(' · ', array_filter([
                $q->quoteNumber,
                $q->stage === 'inviato' && $q->sentAt ? 'inviato ' . F::relativeDays(substr($q->sentAt, 0, 10), $this->today) : null,
                $q->stage === 'inviato' && $q->validUntil ? 'valido fino al ' . F::shortDate($q->validUntil, $this->today) : null,
                $q->stage === 'rifiutato' ? $q->rejectedReason : null,
                $q->stage === 'accettato' && $q->acceptedAt ? 'accettato ' . F::shortDate(substr($q->acceptedAt, 0, 10), $this->today) : null,
            ])),
            'amount' => self::totalLabel($q),
            'badge' => ['label' => (string) $q->stage, 'tone' => self::STAGE_TONES[$q->stage] ?? 'neutral'],
            'actions' => $actions,
        ];
        $by = static fn (string ...$stages) => array_values(array_filter($quotes, static fn (Quote $q) => in_array($q->stage, $stages, true)));
        $recent = date('Y-m-d', strtotime($this->today . ' -90 days'));
        $closed = array_filter($quotes, static fn (Quote $q) => in_array($q->stage, ['accettato', 'rifiutato', 'scaduto'], true) && (string) $q->quoteDate >= $recent);
        $won = array_filter($closed, static fn (Quote $q) => $q->stage === 'accettato');
        $open = $by('bozza', 'da approvare', 'approvato', 'inviato');

        return [
            'totals' => [
                'openValue' => array_sum(array_map(static fn (Quote $q) => (float) $q->totalOneOff + (float) $q->totalYearly, $open)),
                'openCount' => count($open),
                'toApprove' => count($by('da approvare')),
                'sent' => count($by('inviato')),
                'winRate' => count($closed) > 0 ? (int) round(count($won) / count($closed) * 100) : null,
                'wonValue' => array_sum(array_map(static fn (Quote $q) => (float) $q->totalOneOff + (float) $q->totalYearly, $won)),
            ],
            'sections' => array_values(array_filter([
                $canApprove ? ['title' => 'Da approvare', 'tone' => 'warning', 'empty' => 'Niente da approvare.', 'rows' => array_map(static fn (Quote $q) => $row($q, [
                    ['label' => 'Approva', 'action' => '/preventivi/' . $q->id . '/passo', 'body' => ['passo' => 'approva'], 'variant' => 'primary', 'icon' => 'check'],
                ]), $by('da approvare'))] : null,
                ['title' => 'In preparazione', 'tone' => 'neutral', 'empty' => 'Nessuna bozza.', 'rows' => array_map($row, array_merge($by('bozza', 'approvato'), $canApprove ? [] : $by('da approvare')))],
                ['title' => 'Dal cliente', 'tone' => 'info', 'empty' => 'Nessun preventivo in attesa del cliente.', 'rows' => array_map($row, $by('inviato'))],
                ['title' => 'Chiusi negli ultimi 90 giorni', 'tone' => 'neutral', 'empty' => 'Nessuno.', 'rows' => array_map($row, array_values($closed))],
            ])),
        ];
    }

    public function detail(Quote $quote, bool $canApprove): array
    {
        $customer = $this->container->get(CustomerRepository::class)->find((int) $quote->customerId);
        $owner = $quote->ownerUserId !== null ? $this->container->get(UserRepository::class)->find((int) $quote->ownerUserId) : null;
        $approver = $quote->approvedBy !== null ? $this->container->get(UserRepository::class)->find((int) $quote->approvedBy) : null;
        $lines = $this->container->get(QuoteLineRepository::class)->findByQuote((int) $quote->id);
        $base = '/preventivi/' . $quote->id;
        $step = static fn (string $passo, string $label, string $variant = 'primary', array $extra = []) => ['label' => $label, 'action' => "{$base}/passo", 'body' => ['passo' => $passo], 'variant' => $variant] + $extra;

        $actions = match ($quote->stage) {
            'bozza' => [$step('approvazione', $canApprove ? 'Approva' : 'Chiedi approvazione')],
            'da approvare' => $canApprove ? [$step('approva', 'Approva'), $step('rivedi', 'Da rivedere', 'secondary')] : [],
            'approvato' => [
                $step('inviato', 'Segna inviato'),
                ['label' => 'Link per il cliente', 'action' => "{$base}/link-cliente", 'variant' => 'secondary', 'icon' => 'link'],
            ],
            'inviato', 'scaduto' => [
                $step('accettato', 'Il cliente ha accettato', 'primary', ['ask' => 'Come ha accettato?', 'placeholder' => 'Es. firmato, email, WhatsApp']),
                $step('rifiutato', 'Non accetta', 'secondary', ['ask' => 'Perche\'? (serve per i prossimi)', 'placeholder' => 'Es. prezzo, tempi, ha scelto un altro']),
                ['label' => 'Link per il cliente', 'action' => "{$base}/link-cliente", 'variant' => 'ghost', 'icon' => 'link'],
            ],
            default => [],
        };
        $actions[] = ['label' => 'Stampa / PDF', 'href' => "{$base}/stampa", 'variant' => 'ghost', 'icon' => 'download', 'target' => '_blank'];

        return [
            'number' => $quote->quoteNumber,
            'title' => (string) $quote->title,
            'stage' => (string) $quote->stage,
            'stageTone' => self::STAGE_TONES[$quote->stage] ?? 'neutral',
            'customer' => $customer?->displayName(),
            'customerHref' => $customer !== null ? '/clienti/' . $customer->id : null,
            'date' => F::longDate($quote->quoteDate),
            'validUntil' => $quote->validUntil ? F::longDate($quote->validUntil) : null,
            'owner' => $owner !== null ? trim("{$owner->firstName} {$owner->lastName}") : null,
            'approval' => $quote->approvedAt !== null ? 'Approvato' . ($approver !== null ? ' da ' . trim("{$approver->firstName} {$approver->lastName}") : '') . ' il ' . F::longDate(substr($quote->approvedAt, 0, 10)) : null,
            'acceptance' => $quote->acceptedAt !== null ? 'Accettato il ' . F::longDate(substr($quote->acceptedAt, 0, 10)) . ($quote->acceptedVia ? " ({$quote->acceptedVia})" : '') : null,
            'intro' => $quote->intro,
            'conditions' => $quote->conditions,
            'paymentTerms' => $quote->paymentTerms,
            'revisions' => $quote->includedRevisions,
            'discount' => (float) $quote->discountPercent > 0 ? rtrim(rtrim(number_format((float) $quote->discountPercent, 2, ',', ''), '0'), ',') . '%' : null,
            'lines' => array_map(fn (QuoteLine $l) => $this->lineData($l, $quote->isEditable()), $lines),
            'totals' => array_values(array_filter([
                (float) $quote->totalOneOff > 0 ? ['label' => 'Una tantum', 'value' => F::euro((float) $quote->totalOneOff) . ' + IVA'] : null,
                (float) $quote->totalYearly > 0 ? ['label' => 'Ricorrente', 'value' => F::euro((float) $quote->totalYearly) . " all'anno + IVA"] : null,
            ])),
            'problems' => $quote->isEditable() ? $this->container->get(QuoteService::class)->problems($quote) : [],
            'editable' => $quote->isEditable(),
            'newLineHref' => $quote->isEditable() ? '/preventivi/righe/nuovo?quote_id=' . $quote->id . '&quantity=1&billing_type=una%20tantum&payment_plan=unica%20soluzione&position=' . (count($lines) + 1) : null,
            'actions' => $actions,
        ];
    }

    private function lineData(QuoteLine $line, bool $editable): array
    {
        $qty = (float) ($line->quantity ?? 1);

        return [
            'href' => $editable ? '/preventivi/righe/' . $line->id : null,
            'description' => (string) $line->description,
            'details' => $line->details,
            'quantity' => $qty != 1 ? rtrim(rtrim(number_format($qty, 2, ',', ''), '0'), ',') . ' x ' . F::euro((float) $line->unitPrice) : null,
            'amount' => F::euro($line->amount()) . ($line->isRecurring() ? ' ' . (self::PERIOD_LABELS[$line->recurrence] ?? '') : ''),
            'plan' => $line->paymentPlan && $line->paymentPlan !== 'unica soluzione'
                ? ($line->paymentPlan === 'rate' ? ($line->installmentsCount ?: 1) . ' rate' : 'acconto ' . rtrim(rtrim(number_format((float) ($line->depositPercent ?? 50), 2, ',', ''), '0'), ',') . '% e saldo')
                : null,
            'serviceHref' => $line->customerServiceId !== null ? '/servizi-clienti/' . $line->customerServiceId : null,
        ];
    }

    public static function totalLabel(Quote $quote): string
    {
        $parts = array_filter([
            (float) $quote->totalOneOff > 0 ? F::euro((float) $quote->totalOneOff) : null,
            (float) $quote->totalYearly > 0 ? F::euro((float) $quote->totalYearly) . '/anno' : null,
        ]);

        return $parts !== [] ? implode(' + ', $parts) : '—';
    }
}
