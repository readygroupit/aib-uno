<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\CustomerRepository;
use App\View\Component\ClaimTableComponent;
use App\View\Component\StatBoxComponent;

/**
 * Riscritta da zero (era un uso semplice di AbstractListEntityTool, 5
 * colonne anagrafiche piatte): un operatore Assilevi che apre "Clienti"
 * vuole vedere la situazione della pratica di ognuno, non solo nome/
 * email/telefono - stessa idea gia' applicata al cruscotto home, qui
 * per-cliente. Vedi App\Repository\CustomerRepository::
 * findWithClaimSummary() per la query, App\View\Component\
 * ClaimTableComponent per la forma dati, public/js/components/
 * claim-table.js per il disegno (tabella + pannello dettaglio laterale +
 * percorso pratica).
 *
 * Vocabolario di stato FISSO (5 valori, non piu' libero come "nuova/in
 * corso/chiusa" di prima) perche' rispecchia il ciclo di vita reale di
 * un reclamo Assilevi, non lo stato generico di una entita' qualunque -
 * vedi STAGE_META qui sotto, l'unica fonte di etichetta/colore/ordine.
 */
final class ListCustomersTool implements PromptToolInterface, PromptConversationalInterface
{
    /**
     * valore stage => [badge variant, ordine nel percorso pratica 1-4].
     * Mapping colori allineato al riferimento esterno confermato
     * dall'utente (non piu' una scelta arbitraria): 'reclamo inviato' e'
     * un neutro deciso (non teal), 'in conciliazione' e' il teal (non il
     * verde di 'success', che nel riferimento non e' mai usato per uno
     * stage) - vedi badge--strong in kit.css.
     */
    private const STAGE_META = [
        'raccolta documenti' => ['variant' => 'accent', 'step' => 1],
        'reclamo inviato' => ['variant' => 'strong', 'step' => 2],
        'in conciliazione' => ['variant' => 'info', 'step' => 3],
        'rimborsato' => ['variant' => 'warning', 'step' => 4],
        'respinta' => ['variant' => 'danger', 'step' => 4],
    ];

    private const TIMELINE_STEPS = ['Lead ricevuto', 'Documenti completi', 'Reclamo al vettore', 'Conciliazione', 'Rimborso'];

    /** Giorni di attesa prima del sollecito automatico al vettore - default dichiarato, non una vera configurazione per pratica (non esiste ancora un campo SLA sulla pratica). */
    private const FOLLOWUP_SLA_DAYS = 14;

    private CustomerRepository $customers;
    private StatBoxComponent $statBox;
    private ClaimTableComponent $claimTable;

    public function __construct(Container $container)
    {
        $this->customers = $container->get(CustomerRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->claimTable = $container->get(ClaimTableComponent::class);
    }

    public function name(): string
    {
        return 'list_customers';
    }

    public function description(): string
    {
        return "Mostra l'elenco dei clienti con la situazione della loro pratica in corso.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['page' => ['type' => 'integer']]];
    }

    public function menuLabel(): ?string
    {
        return 'Clienti';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function menuCount(): ?string
    {
        return (string) $this->customers->count();
    }

    public function requiredPermission(): ?string
    {
        return 'customers.view';
    }

    public function triggers(): array
    {
        return ['clienti', 'lista clienti', 'elenco clienti'];
    }

    public function execute(array $input): array
    {
        $data = $this->customers->findWithClaimSummary();

        $rows = array_map(fn (array $entry) => $this->buildRow($entry), $data);

        return [$this->claimTable->toData([
            'title' => 'Clienti',
            'url' => '/clienti',
            'createHref' => '/clienti/nuovo',
            'createLabel' => 'Nuovo cliente',
            'exportHref' => '/clienti/esporta',
            'stats' => $this->buildStats($data),
            'filterTabs' => $this->buildFilterTabs($rows),
            'rows' => $rows,
            'emptyMessage' => 'Nessun cliente. Aggiungine uno per iniziare.',
        ])];
    }

    /**
     * Frase reale sul risultato appena calcolato, non un segnaposto -
     * ricalcola findWithClaimSummary() (stesso motivo del commento su
     * CustomerRepository: un giro in piu' accettabile ai volumi di una
     * demo). Chiamata solo quando il match e' locale/gratuito (vedi
     * PromptController::respondWithTool()), mai da Claude - quello
     * scrive gia' la propria frase.
     */
    public function replySummary(array $input): ?string
    {
        $data = $this->customers->findWithClaimSummary();
        $total = count($data);
        if ($total === 0) {
            return 'Non ci sono ancora clienti registrati.';
        }

        $withDocsIncomplete = 0;
        $inConciliazione = 0;
        $companyContacts = [];
        foreach ($data as $entry) {
            if ($entry['docsTotal'] > 0 && $entry['docsComplete'] < $entry['docsTotal']) {
                $withDocsIncomplete++;
            }
            if (($entry['case']['stage'] ?? null) === 'in conciliazione') {
                $inConciliazione++;
            }
            $customer = $entry['customer'];
            $contactName = trim("{$customer->firstName} {$customer->lastName}");
            if ($customer->companyName !== null && $contactName !== '') {
                $companyContacts[] = $contactName;
            }
        }

        $sentence = $total === 1 ? '1 cliente attivo.' : "{$total} clienti attivi.";

        $parts = [];
        if ($withDocsIncomplete === 1) {
            $parts[] = 'per 1 manca ancora un documento';
        } elseif ($withDocsIncomplete > 1) {
            $parts[] = "per {$withDocsIncomplete} mancano ancora dei documenti";
        }
        if ($inConciliazione === 1) {
            $parts[] = '1 ha una conciliazione aperta';
        } elseif ($inConciliazione > 1) {
            $parts[] = "{$inConciliazione} hanno una conciliazione aperta";
        }
        if ($parts !== []) {
            $sentence .= ' ' . ucfirst(implode(' e ', $parts)) . '.';
        }

        if (count($companyContacts) === 1) {
            $sentence .= " {$companyContacts[0]} e' l'unica con pratica aziendale.";
        } elseif (count($companyContacts) > 1) {
            $sentence .= ' ' . count($companyContacts) . ' hanno una pratica aziendale.';
        }

        return $sentence;
    }

    public function followUpSuggestions(): array
    {
        return [
            'Chi ha documenti mancanti?',
            'Clienti sullo stesso volo',
            'Pratiche respinte da valutare',
        ];
    }

    /** @param list<array> $data vedi CustomerRepository::findWithClaimSummary() */
    private function buildStats(array $data): array
    {
        $withDocsIncomplete = 0;
        $inConciliazione = 0;
        $totalClaimed = 0.0;
        $totalAccepted = 0.0;

        foreach ($data as $entry) {
            if ($entry['docsTotal'] > 0 && $entry['docsComplete'] < $entry['docsTotal']) {
                $withDocsIncomplete++;
            }
            if (($entry['case']['stage'] ?? null) === 'in conciliazione') {
                $inConciliazione++;
            }
            if ($entry['refund'] !== null) {
                $totalClaimed += (float) ($entry['refund']['amount_claimed'] ?? 0);
                $totalAccepted += (float) ($entry['refund']['amount_accepted'] ?? 0);
            }
        }

        return [
            $this->statBox->toData([
                'label' => 'Totale clienti',
                'value' => (string) count($data),
                'comparison' => 'attivi, esclusi quelli eliminati',
                'dotColor' => 'moss',
            ]),
            $this->statBox->toData([
                'label' => 'Documenti incompleti',
                'value' => (string) $withDocsIncomplete,
                'comparison' => 'da sollecitare al cliente',
                'dotColor' => 'lilac',
            ]),
            $this->statBox->toData([
                'label' => 'In conciliazione',
                'value' => (string) $inConciliazione,
                'comparison' => 'pratiche aperte su ConciliaWeb',
                'dotColor' => 'moss',
            ]),
            $this->statBox->toData([
                'label' => 'Importo in gioco',
                'value' => number_format($totalClaimed, 0, ',', '.') . ' €',
                'comparison' => number_format($totalAccepted, 0, ',', '.') . ' € gia\' recuperati',
                'dotColor' => 'brass',
                'hero' => true,
            ]),
        ];
    }

    /** @param list<array> $rows gia' costruite da buildRow() */
    private function buildFilterTabs(array $rows): array
    {
        $counts = ['tutti' => count($rows), 'documenti_mancanti' => 0, 'in_corso' => 0, 'chiuse' => 0];
        foreach ($rows as $row) {
            $counts[$row['filterBucket']] = ($counts[$row['filterBucket']] ?? 0) + 1;
        }

        return [
            ['label' => 'Tutti', 'value' => 'tutti', 'count' => $counts['tutti']],
            ['label' => 'Documenti mancanti', 'value' => 'documenti_mancanti', 'count' => $counts['documenti_mancanti']],
            ['label' => 'In corso', 'value' => 'in_corso', 'count' => $counts['in_corso']],
            ['label' => 'Chiuse', 'value' => 'chiuse', 'count' => $counts['chiuse']],
        ];
    }

    private function buildRow(array $entry): array
    {
        $customer = $entry['customer'];
        $case = $entry['case'];
        $lead = $entry['lead'];
        $refund = $entry['refund'];

        $name = trim("{$customer->firstName} {$customer->lastName}") ?: ($customer->companyName ?? '(senza nome)');
        $initials = mb_strtoupper(mb_substr((string) $customer->firstName, 0, 1) . mb_substr((string) $customer->lastName, 0, 1));
        if (trim($initials) === '') {
            $initials = mb_strtoupper(mb_substr((string) $customer->companyName, 0, 2));
        }

        $stage = $case['stage'] ?? null;
        $stageMeta = self::STAGE_META[$stage] ?? null;
        $docsTotal = $entry['docsTotal'];
        $docsComplete = $entry['docsComplete'];
        $isCompany = $customer->companyName !== null && trim("{$customer->firstName}{$customer->lastName}") !== '';

        return [
            'id' => $customer->id,
            'avatarInitials' => $initials,
            'isCompany' => $isCompany,
            'name' => $name,
            'companyTag' => $isCompany ? 'Azienda' : null,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'flightLabel' => $lead !== null && $lead['flight_route'] !== null
                ? trim(($lead['airline'] ?? '') . ' · ' . $this->arrowRoute($lead['flight_route']))
                : null,
            'disservizioLabel' => $lead['disservice_type'] ?? null,
            'stageLabel' => $stage !== null ? ucfirst($stage) : null,
            'stageVariant' => $stageMeta['variant'] ?? 'neutral',
            'docsTotal' => $docsTotal,
            'docsComplete' => $docsComplete,
            'amountValue' => $refund !== null ? number_format((float) ($refund['amount_claimed'] ?? 0), 0, ',', '.') . ' €' : null,
            'amountCaption' => $this->amountCaption($refund),
            'filterBucket' => $this->filterBucket($stage, $docsTotal, $docsComplete),
            'detail' => $this->buildDetail($customer, $case, $lead, $refund, $name, $isCompany, $stage, $stageMeta, $docsTotal, $docsComplete),
        ];
    }

    /** "BGY-BCN" -> "BGY → BCN": stessa tratta reale, solo tipografia (mai un dato inventato). */
    private function arrowRoute(string $route): string
    {
        return str_replace('-', ' → ', $route);
    }

    private function amountCaption(?array $refund): ?string
    {
        if ($refund === null) {
            return null;
        }

        return $refund['payment_status'] === 'pagato' ? 'recuperati' : 'richiesti';
    }

    private function filterBucket(?string $stage, int $docsTotal, int $docsComplete): string
    {
        if ($docsTotal > 0 && $docsComplete < $docsTotal) {
            return 'documenti_mancanti';
        }
        if (in_array($stage, ['rimborsato', 'respinta'], true)) {
            return 'chiuse';
        }

        return 'in_corso';
    }

    private function buildDetail(
        object $customer,
        ?array $case,
        ?array $lead,
        ?array $refund,
        string $name,
        bool $isCompany,
        ?string $stage,
        ?array $stageMeta,
        int $docsTotal,
        int $docsComplete
    ): array {
        return [
            'name' => $name,
            // Il nome vero dell'azienda (non la generica etichetta "Azienda"),
            // e' un dato reale gia' sul cliente - piu' utile a colpo d'occhio.
            'typeLabel' => $isCompany ? $customer->companyName : 'Privato',
            'volo' => $lead !== null && $lead['flight_route'] !== null ? $this->arrowRoute($lead['flight_route']) : null,
            'vettore' => $lead['airline'] ?? null,
            'disservizio' => $lead['disservice_type'] ?? null,
            'importo' => $refund !== null ? number_format((float) ($refund['amount_claimed'] ?? 0), 0, ',', '.') . ' € richiesti' : null,
            'fonte' => $lead['source_channel'] ?? $lead['campaign'] ?? null,
            'timeline' => $this->buildTimeline($case, $lead, $stageMeta, $docsTotal, $docsComplete),
            'suggestedAction' => $this->suggestedAction($case, $stage),
            'editHref' => '/clienti/' . $customer->id,
            'openCaseHref' => $case !== null ? '/pratiche/' . $case['id'] : null,
        ];
    }

    /** @return list<array{label:string,date:?string,state:string}> state: done|current|future */
    private function buildTimeline(?array $case, ?array $lead, ?array $stageMeta, int $docsTotal, int $docsComplete): array
    {
        if ($case === null) {
            return array_map(static fn (string $label) => ['label' => $label, 'date' => null, 'state' => 'future'], self::TIMELINE_STEPS);
        }

        // step corrente: 1 (raccolta documenti) finche' i documenti non sono tutti completi,
        // altrimenti la posizione dichiarata dallo stage - cosi' "documenti completi" si accende
        // davvero solo quando lo sono, non solo perche' la pratica e' avanzata di stato a mano.
        $docsReallyComplete = $docsTotal > 0 && $docsComplete === $docsTotal;
        $currentStep = $stageMeta['step'] ?? 1;
        if (!$docsReallyComplete && $currentStep < 2) {
            $currentStep = 1;
        }

        $dates = [
            1 => $lead['created_at'] ?? $case['opened_at'] ?? null,
            2 => $docsReallyComplete ? ($case['updated_at'] ?? null) : null,
            3 => $currentStep >= 3 ? ($case['updated_at'] ?? null) : null,
            4 => $currentStep >= 4 ? ($case['updated_at'] ?? null) : null,
            5 => $case['closed_at'] ?? null,
        ];

        $timeline = [];
        foreach (self::TIMELINE_STEPS as $i => $label) {
            $step = $i + 1;
            $state = $step < $currentStep + 1 ? 'done' : ($step === $currentStep + 1 ? 'current' : 'future');
            // Il primo passo "Lead ricevuto" e' sempre gia' fatto (la pratica esiste).
            if ($step === 1) {
                $state = 'done';
            }
            $isRespintaOutcome = $step === 5 && ($stageMeta['variant'] ?? null) === 'danger';
            $timeline[] = [
                'label' => $isRespintaOutcome ? 'Esito: respinta' : $label,
                'date' => $dates[$step] !== null ? date('d/m', strtotime($dates[$step])) : null,
                'state' => $state,
                // Il pallino "corrente" e' oro ovunque tranne qui: l'esito
                // di una pratica respinta non e' un traguardo da festeggiare
                // con lo stesso colore di una in corso - vedi claim-table.js.
                'danger' => $isRespintaOutcome,
            ];
        }

        return $timeline;
    }

    private function suggestedAction(?array $case, ?string $stage): ?string
    {
        if ($case === null || $stage !== 'reclamo inviato') {
            return null;
        }

        $updatedAt = $case['updated_at'] ?? $case['created_at'];
        $daysSince = (int) floor((time() - strtotime((string) $updatedAt)) / 86400);
        $daysLeft = self::FOLLOWUP_SLA_DAYS - $daysSince;

        if ($daysLeft <= 0) {
            return 'Sollecito al vettore consigliato ora: nessuna risposta da oltre ' . self::FOLLOWUP_SLA_DAYS . ' giorni.';
        }

        return "Sollecito automatico al vettore tra {$daysLeft} giorni se non risponde.";
    }
}
