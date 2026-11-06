<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Package\PackageCatalogService;
use App\Repository\CaseFileRepository;
use App\Repository\CommunicationRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\LeadRepository;
use App\Repository\RefundRepository;
use App\Repository\TaskRepository;
use App\View\Component\BreakdownComponent;
use App\View\Component\DashboardHeaderComponent;
use App\View\Component\StatBoxComponent;

/**
 * Riepilogo numerico multi-pacchetto (agente "Management Reporter" del
 * brief Assilevi, punto 6 dell'MVP consigliato) - bespoke apposta, non
 * rappresenta UNA entita' ma incrocia contatori di piu' repository.
 */
final class ShowReportTool implements PromptToolInterface
{
    private Container $container;
    private LeadRepository $leads;
    private CaseFileRepository $cases;
    private DocumentRequestRepository $documentRequests;
    private CommunicationRepository $communications;
    private TaskRepository $tasks;
    private RefundRepository $refunds;
    private StatBoxComponent $statBox;

    private const MESI = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    public function __construct(Container $container)
    {
        $this->leads = $container->get(LeadRepository::class);
        $this->cases = $container->get(CaseFileRepository::class);
        $this->documentRequests = $container->get(DocumentRequestRepository::class);
        $this->communications = $container->get(CommunicationRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
        $this->refunds = $container->get(RefundRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->container = $container;
    }

    public function name(): string
    {
        return 'show_report';
    }

    public function description(): string
    {
        return "Mostra il report riepilogativo con totali e grafici: valore dei rimborsi, pratiche "
            . "aperte e per stato, nuovi contatti e comunicazioni degli ultimi 7 giorni, attivita' in "
            . "scadenza, documenti e rimborsi per stato. Usalo per riepiloghi, resoconti, statistiche, "
            . "totali, andamento della settimana o del periodo, situazione generale.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    /** Il report delle pratiche: nel menu solo dove c'e' almeno uno dei suoi pacchetti. */
    public function menuLabel(): ?string
    {
        return array_intersect(['cases', 'leads', 'refunds'], PackageCatalogService::installedNames()) !== [] ? 'Report' : null;
    }

    public function menuSection(): ?string
    {
        return 'sistema';
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return 'reports.view';
    }

    public function triggers(): array
    {
        return ['report', 'riepilogo', 'report giornaliero', 'report settimanale'];
    }

    public function execute(array $input): array
    {
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
        $threeDaysAhead = date('Y-m-d H:i:s', strtotime('+3 days'));
        $from = strtotime('-6 days');
        $period = (int) date('j', $from) . (date('n', $from) !== date('n') ? ' ' . self::MESI[(int) date('n', $from)] : '')
            . ' - ' . (int) date('j') . ' ' . self::MESI[(int) date('n')];
        // Ogni blocco solo se il suo pacchetto c'e' in questo progetto (senza, non c'e' la tabella).
        $has = static fn (string $package) => in_array($package, PackageCatalogService::installedNames(), true);

        $components = [
            $this->container->get(DashboardHeaderComponent::class)->toData([
                'dateLabel' => mb_strtoupper('Report settimanale · ' . $period),
                'greeting' => 'Com\'e\' andata questa settimana',
            ]),
        ];
        // Ordine di lettura: prima il valore economico (card protagonista),
        // poi i contatori, poi come stanno le pratiche.
        if ($has('refunds')) {
            $thisWeek = $this->refunds->sumAcceptedBetween(date('Y-m-d', strtotime('-6 days')), date('Y-m-d', strtotime('+1 day')));
            $components[] = $this->statBox->toData([
                'label' => 'Valore rimborsi accettati',
                'value' => number_format($this->refunds->sumAcceptedAmount(), 2, ',', '.') . ' €',
                'comparison' => 'somma di tutte le pratiche · crescita giorno per giorno',
                'href' => '/rimborsi',
                'feature' => true,
                'badge' => $thisWeek > 0 ? '+' . number_format($thisWeek, 0, ',', '.') . ' € in 7 giorni' : null,
                'sparkline' => $this->refunds->sumAcceptedCumulativeLast7(),
                'sparklineStyle' => 'bars',
            ]);
        }
        if ($has('cases')) {
            $components[] = $this->stat('Pratiche aperte', (string) $this->cases->count(), 'attive, escluse quelle eliminate', '/pratiche', 'petrol', false, $this->cases->countPerDayLast7('created_at'));
        }
        if ($has('leads')) {
            $components[] = $this->stat('Nuovi contatti', (string) $this->leads->countCreatedSince($weekAgo), 'ultimi 7 giorni, giorno per giorno', '/contatti', 'moss', false, $this->leads->countPerDayLast7('created_at'));
        }
        if ($has('communications')) {
            $components[] = $this->stat('Comunicazioni inviate', (string) $this->communications->countSentSince($weekAgo), 'ultimi 7 giorni, giorno per giorno', '/comunicazioni', 'teal', false, $this->communications->countPerDayLast7('sent_at'));
        }
        if ($has('tasks')) {
            $components[] = $this->stat('Attivita\' in scadenza', (string) $this->tasks->countDueBy($threeDaysAhead), 'non completate, entro 3 giorni', '/attivita', 'lilac');
        }
        if ($has('leads')) {
            $components[] = $this->stat('Duplicati sospetti', (string) count($this->leads->findDuplicateCandidates(200)), 'da rivedere in "Duplicati contatti"', '/contatti/duplicati', 'brass');
        }

        $groups = [];
        if ($has('cases')) {
            $cases = $this->cases->countGroupedBy('stage');
            $inConciliation = (int) ($cases['in conciliazione'] ?? 0);
            $groups[] = ['title' => 'Pratiche per stato', 'href' => '/pratiche', 'counts' => $cases, 'highlight' => $inConciliation > 0 ? ['text' => "{$inConciliation} in conciliazione", 'tone' => 'warning'] : null];
        }
        if ($has('document_requests')) {
            $documents = $this->documentRequests->countGroupedBy('completeness_status');
            $documentsTotal = array_sum($documents);
            $completeDocuments = (int) ($documents['completo'] ?? 0);
            $groups[] = ['title' => 'Documenti', 'href' => '/documenti-richiesti', 'counts' => $documents, 'highlight' => $documentsTotal > 0 ? ['text' => round($completeDocuments * 100 / $documentsTotal) . '% completi', 'tone' => 'success'] : null];
        }
        if ($has('refunds')) {
            $refunds = $this->refunds->countGroupedBy('payment_status');
            $paid = (int) ($refunds['pagato'] ?? 0);
            $groups[] = ['title' => 'Rimborsi per pagamento', 'href' => '/rimborsi', 'counts' => $refunds, 'highlight' => $paid > 0 ? ['text' => $paid === 1 ? '1 pagato' : "{$paid} pagati", 'tone' => 'success'] : null];
        }
        if ($groups !== []) {
            $components[] = $this->container->get(BreakdownComponent::class)->toData([
                'title' => 'Come stanno le pratiche',
                'subtitle' => 'Clicca un riquadro per aprire l\'elenco.',
                'groups' => $groups,
            ]);
        }

        return $components;
    }

    /** @param int[]|null $sparkline */
    private function stat(string $label, string $value, string $comparison, string $href, string $dotColor, bool $hero = false, ?array $sparkline = null): array
    {
        return $this->statBox->toData([
            'label' => $label,
            'value' => $value,
            'comparison' => $comparison,
            'href' => $href,
            'dotColor' => $dotColor,
            'hero' => $hero,
            'sparkline' => $sparkline,
            'sparklineStyle' => 'bars',
        ]);
    }
}
