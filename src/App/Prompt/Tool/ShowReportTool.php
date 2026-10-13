<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
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
        return "Mostra il report riepilogativo con totali e tabelle: valore dei rimborsi, pratiche "
            . "aperte e per stato, nuovi contatti e comunicazioni degli ultimi 7 giorni, attivita' in "
            . "scadenza, documenti e rimborsi per stato. Usalo per riepiloghi, resoconti, statistiche, "
            . "totali, andamento della settimana o del periodo, situazione generale.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function menuLabel(): ?string
    {
        return 'Report';
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
        $accepted = $this->refunds->sumAcceptedAmount();
        $from = strtotime('-6 days');
        $period = (int) date('j', $from) . (date('n', $from) !== date('n') ? ' ' . self::MESI[(int) date('n', $from)] : '')
            . ' - ' . (int) date('j') . ' ' . self::MESI[(int) date('n')];

        return [
            $this->container->get(DashboardHeaderComponent::class)->toData([
                'dateLabel' => mb_strtoupper('Report settimanale · ' . $period),
                'greeting' => 'Com\'e\' andata questa settimana',
            ]),
            $this->stat('Valore rimborsi accettati', number_format($accepted, 2, ',', '.') . ' €', 'somma di tutte le pratiche', '/rimborsi', 'teal', true),
            $this->stat('Pratiche aperte', (string) $this->cases->count(), 'attive, escluse quelle eliminate', '/pratiche', 'lilac'),
            $this->stat('Nuovi contatti', (string) $this->leads->countCreatedSince($weekAgo), 'ultimi 7 giorni, giorno per giorno', '/contatti', 'moss', false, $this->leads->countPerDayLast7('created_at')),
            $this->stat('Comunicazioni inviate', (string) $this->communications->countSentSince($weekAgo), 'ultimi 7 giorni, giorno per giorno', '/comunicazioni', 'teal', false, $this->communications->countPerDayLast7('sent_at')),
            $this->stat('Attivita\' in scadenza', (string) $this->tasks->countDueBy($threeDaysAhead), 'non completate, entro 3 giorni', '/attivita', 'lilac'),
            $this->stat('Duplicati contatti sospetti', (string) count($this->leads->findDuplicateCandidates(200)), 'da rivedere in "Duplicati contatti"', '/contatti/duplicati', 'brass'),
            $this->container->get(BreakdownComponent::class)->toData([
                'title' => 'Come stanno le pratiche',
                'subtitle' => 'Verde: fatto · giallo: in corso · rosso: da sbloccare. Clicca un riquadro per aprire l\'elenco.',
                'groups' => [
                    ['title' => 'Pratiche per stato', 'href' => '/pratiche', 'counts' => $this->cases->countGroupedBy('stage')],
                    ['title' => 'Documenti', 'href' => '/documenti-richiesti', 'counts' => $this->documentRequests->countGroupedBy('completeness_status')],
                    ['title' => 'Rimborsi per pagamento', 'href' => '/rimborsi', 'counts' => $this->refunds->countGroupedBy('payment_status')],
                ],
            ]),
        ];
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
