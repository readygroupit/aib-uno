<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\CaseFileRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\LeadRepository;
use App\Repository\ProfileRepository;
use App\Repository\RefundRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\View\Component\AgentPanelComponent;
use App\View\Component\ApprovalQueueComponent;
use App\View\Component\DashboardHeaderComponent;
use App\View\Component\FunnelComponent;
use App\View\Component\StatBoxComponent;

/**
 * Cruscotto della home: intestazione (chi sei, che giorno e', su quale
 * intervallo stai guardando), contatori cliccabili, il ciclo di vita
 * completo lead -> rimborso come imbuto, la coda "da approvare" (human in
 * the loop) e lo stato degli agenti - traduzione diretta della struttura
 * del brief Assilevi (6 Agenti AI, 7 Flusso operativo, 10 Livelli di
 * automazione) in sezioni di schermata, non solo contatori isolati (vedi
 * la nota di App\View\Component\AgentPanelComponent sul perche' non tutto
 * qui e' "autonomo" per davvero).
 */
final class ShowHomeDashboardTool implements PromptToolInterface
{
    private LeadRepository $leads;
    private CaseFileRepository $cases;
    private DocumentRequestRepository $documentRequests;
    private TaskRepository $tasks;
    private RefundRepository $refunds;
    private UserRepository $users;
    private ProfileRepository $profiles;
    private AuthService $auth;
    private StatBoxComponent $statBox;
    private DashboardHeaderComponent $header;
    private FunnelComponent $funnel;
    private ApprovalQueueComponent $approvalQueue;
    private AgentPanelComponent $agentPanel;

    private const RANGES = [
        'today' => ['label' => 'Oggi', 'days' => 1],
        '7d' => ['label' => '7 giorni', 'days' => 7],
        '30d' => ['label' => '30 giorni', 'days' => 30],
    ];

    private const GIORNI = ['Domenica', 'Lunedi\'', 'Martedi\'', 'Mercoledi\'', 'Giovedi\'', 'Venerdi\'', 'Sabato'];
    private const MESI = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    public function __construct(Container $container)
    {
        $this->leads = $container->get(LeadRepository::class);
        $this->cases = $container->get(CaseFileRepository::class);
        $this->documentRequests = $container->get(DocumentRequestRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
        $this->refunds = $container->get(RefundRepository::class);
        $this->users = $container->get(UserRepository::class);
        $this->profiles = $container->get(ProfileRepository::class);
        $this->auth = $container->get(AuthService::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->header = $container->get(DashboardHeaderComponent::class);
        $this->funnel = $container->get(FunnelComponent::class);
        $this->approvalQueue = $container->get(ApprovalQueueComponent::class);
        $this->agentPanel = $container->get(AgentPanelComponent::class);
    }

    public function name(): string
    {
        return 'show_home_dashboard';
    }

    public function description(): string
    {
        return "Mostra il cruscotto iniziale: intestazione, contatori, ciclo di vita, coda da approvare e stato agenti.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) ['range' => ['type' => 'string']]];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function menuSection(): ?string
    {
        return null;
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    /**
     * 'home' come frase scorciatoia: serve al pulsante "casa" della
     * toolbar del prompt (vedi hero.js) per tornare alla home restando
     * nel meccanismo SPA esistente (window.unoSubmitPrompt), invece di
     * un link <a href="/"> che ricaricherebbe l'intera pagina perdendo
     * lo storico dei risultati.
     */
    public function triggers(): array
    {
        return ['home', 'torna alla home', 'vai alla home'];
    }

    public function execute(array $input): array
    {
        $range = self::RANGES[$input['range'] ?? ''] ?? null;
        $rangeKey = isset(self::RANGES[$input['range'] ?? '']) ? $input['range'] : 'today';
        $range ??= self::RANGES['today'];

        $components = [
            $this->buildHeader($rangeKey),
            ...$this->buildStatCards($range['days']),
            $this->buildFunnel(),
            $this->buildApprovalQueue(),
            $this->buildAgentPanel(),
        ];

        // Permette a extractUrl() (hero.js) di aggiornare la barra degli
        // indirizzi su '/' come per qualunque altro risultato che
        // corrisponde a una pagina vera - altrimenti il tasto indietro
        // del browser non avrebbe nulla da "disfare" per questo passaggio.
        $components[0]['url'] = '/';

        return $components;
    }

    private function buildHeader(string $rangeKey): array
    {
        $now = time();
        $dateLabel = self::GIORNI[(int) date('w', $now)] . ' ' . (int) date('j', $now) . ' ' . self::MESI[(int) date('n', $now)];

        $userId = $this->auth->currentUserId();
        $user = $userId !== null ? $this->users->find($userId) : null;
        $profile = $user?->profileId !== null ? $this->profiles->find($user->profileId) : null;

        $rangeTabs = [];
        foreach (self::RANGES as $key => $def) {
            $rangeTabs[] = ['label' => $def['label'], 'active' => $key === $rangeKey, 'href' => '/?range=' . $key];
        }

        return $this->header->toData([
            'dateLabel' => mb_strtoupper($dateLabel),
            'greeting' => 'Buongiorno' . ($user?->firstName !== null ? ", {$user->firstName}" : ''),
            'userInitials' => $user !== null ? mb_strtoupper(mb_substr((string) $user->firstName, 0, 1) . mb_substr((string) $user->lastName, 0, 1)) : '',
            'userName' => $user !== null ? trim("{$user->firstName} {$user->lastName}") : '',
            'userRole' => $profile->name ?? '',
            'rangeTabs' => $rangeTabs,
        ]);
    }

    private function buildStatCards(int $rangeDays): array
    {
        $rangeSince = date('Y-m-d H:i:s', strtotime("-{$rangeDays} days"));
        $prevRangeSince = date('Y-m-d H:i:s', strtotime('-' . ($rangeDays * 2) . ' days'));
        $dueBy = date('Y-m-d H:i:s', strtotime("+{$rangeDays} days"));
        $twoDaysAgo = date('Y-m-d H:i:s', strtotime('-48 hours'));

        $newLeads = $this->leads->countBetween('created_at', $rangeSince, date('Y-m-d H:i:s'));
        $prevNewLeads = $this->leads->countBetween('created_at', $prevRangeSince, $rangeSince);
        $delta = $prevNewLeads > 0 ? (int) round((($newLeads - $prevNewLeads) / $prevNewLeads) * 100) : null;

        return [
            $this->card('Contatti da richiamare', (string) $this->leads->countStale($twoDaysAgo), 'fermi da oltre 48 ore', '/contatti', 'danger'),
            $this->card('Duplicati da rivedere', (string) count($this->leads->findDuplicateCandidates(200)), 'possibili pratiche familiari', '/contatti/duplicati', 'brass'),
            $this->card('Documenti mancanti', (string) $this->documentRequests->count(['completeness_status' => 'mancante']), 'da sollecitare al cliente', '/documenti-richiesti', 'lilac'),
            $this->card('Attivita\' in scadenza', (string) $this->tasks->countDueBy($dueBy), 'non completate, entro fine periodo', '/attivita', 'lilac'),
            $this->card('Pratiche aperte', (string) $this->cases->count(), 'attive, escluse le eliminate', '/pratiche', 'teal'),
            $this->statBox->toData([
                'label' => 'Nuovi contatti',
                'value' => (string) $newLeads,
                'delta' => $delta !== null ? ($delta >= 0 ? "+{$delta}%" : "{$delta}%") : null,
                'deltaPositive' => $delta === null || $delta >= 0,
                'comparison' => 'periodo selezionato vs precedente',
                'sparkline' => $this->leads->countPerDayLast7('created_at'),
                'sparklineStyle' => 'bars',
                'href' => '/contatti',
                'dotColor' => 'moss',
            ]),
        ];
    }

    private function card(string $label, string $value, string $comparison, string $href, string $dotColor): array
    {
        return $this->statBox->toData([
            'label' => $label,
            'value' => $value,
            'comparison' => $comparison,
            'href' => $href,
            'dotColor' => $dotColor,
            'sparklineStyle' => 'bars',
            'sparkline' => [0, 0, 0, 0, 0, 0, (int) $value],
        ]);
    }

    /**
     * Le tappe seguono la dipendenza VERA dello schema (i documenti si
     * collegano solo a una pratica gia' aperta, vedi document_requests.
     * case_id 'base') invece dell'ordine letterale del brief (che
     * controlla i documenti mentre e' ancora un lead) - stesso ciclo di
     * vita, riordinato secondo cosa esiste davvero qui.
     */
    private function buildFunnel(): array
    {
        $totalLeads = $this->leads->count();
        $qualifiedCount = $totalLeads - ($this->leads->countGroupedBy('stage')['nuovo'] ?? 0);
        $casesTotal = $this->cases->count();
        $docsComplete = $this->documentRequests->count(['completeness_status' => 'completo']);
        $inConciliation = $this->cases->count(['stage' => 'in conciliazione']);
        $refundedCount = $this->refunds->count(['payment_status' => 'pagato']);
        $refundedAmount = $this->refunds->sumAcceptedAmount();

        $pct = static fn (int $a, int $b): string => $b > 0 ? round(($a / $b) * 100) . '% del precedente' : '';

        return $this->funnel->toData([
            'title' => 'Ciclo di vita · ultimi 30 giorni',
            'subtitle' => 'Dal contatto al rimborso',
            'summaryLabel' => 'Conversione lead -> pratica',
            'summaryValue' => $totalLeads > 0 ? round(($casesTotal / $totalLeads) * 100) . '%' : '-',
            'stages' => [
                ['label' => 'Lead', 'value' => $totalLeads, 'caption' => 'da Jotform e landing', 'href' => '/contatti'],
                ['label' => 'Qualificati', 'value' => $qualifiedCount, 'caption' => $pct($qualifiedCount, $totalLeads), 'href' => '/contatti'],
                ['label' => 'Pratica avviata', 'value' => $casesTotal, 'caption' => $pct($casesTotal, $qualifiedCount), 'href' => '/pratiche'],
                ['label' => 'Documenti completi', 'value' => $docsComplete, 'caption' => $pct($docsComplete, $casesTotal), 'href' => '/documenti-richiesti'],
                ['label' => 'Conciliazione', 'value' => $inConciliation, 'caption' => $pct($inConciliation, $docsComplete), 'href' => '/pratiche'],
                ['label' => 'Rimborsati', 'value' => $refundedCount, 'caption' => number_format($refundedAmount, 0, ',', '.') . ' € recuperati', 'href' => '/rimborsi'],
            ],
        ]);
    }

    /**
     * Solo 3 tipi di card (contatti fermi, duplicati, documenti mancanti):
     * quelli su cui esiste davvero una rilevazione + un'azione concreta
     * dietro (vedi LeadRepository::findStale()/findDuplicateCandidates(),
     * LeadsController::markContactedAction(), DocumentRequestsController::
     * remindAction()). Niente card per bozze legali o verifiche di
     * fatturazione: non esiste ancora nulla di reale dietro, meglio
     * un'assenza onesta che una card che non fa nulla.
     */
    private function buildApprovalQueue(): array
    {
        $items = [];

        foreach ($this->leads->findStale(date('Y-m-d H:i:s', strtotime('-48 hours')), 2) as $lead) {
            $hours = (int) floor((time() - strtotime((string) $lead->createdAt)) / 3600);
            $items[] = [
                'avatarInitials' => mb_strtoupper(mb_substr((string) $lead->firstName, 0, 1) . mb_substr((string) $lead->lastName, 0, 1)),
                'avatarColor' => '#8e3a29',
                'tag' => 'Richiamo',
                'tagColor' => 'danger',
                'title' => trim("{$lead->firstName} {$lead->lastName}"),
                'description' => "Fermo da {$hours} ore" . ($lead->flightRoute !== null ? " · volo {$lead->flightRoute}" : ''),
                'agentLabel' => 'Follow-up commerciale',
                'reviewHref' => '/contatti/' . $lead->id,
                'approveUrl' => '/contatti/' . $lead->id . '/contattato',
            ];
        }

        foreach ($this->leads->findDuplicateCandidates(2) as $dup) {
            $items[] = [
                'avatarInitials' => mb_strtoupper(mb_substr($dup['bLabel'], 0, 2)),
                'avatarColor' => '#8a6a1f',
                'tag' => 'Duplicato',
                'tagColor' => 'brass',
                'title' => $dup['bLabel'],
                'description' => ucfirst($dup['reason']) . " con {$dup['aLabel']}",
                'agentLabel' => 'Lead Intake e Data Quality',
                'reviewHref' => '/contatti/duplicati/' . $dup['bId'],
                'approveUrl' => null,
            ];
        }

        foreach ($this->documentRequests->findMissingWithCase(2) as $doc) {
            $items[] = [
                'avatarInitials' => mb_strtoupper(mb_substr($doc['caseTitle'], 0, 2)),
                'avatarColor' => '#5b5480',
                'tag' => 'Documenti',
                'tagColor' => 'lilac',
                'title' => $doc['caseTitle'],
                'description' => "Manca: {$doc['documentType']}",
                'agentLabel' => 'Document Manager',
                'reviewHref' => '/documenti-richiesti/' . $doc['id'],
                'approveUrl' => '/documenti-richiesti/' . $doc['id'] . '/sollecita',
            ];
        }

        return $this->approvalQueue->toData([
            'title' => 'Human in the loop',
            'subtitle' => 'Da approvare',
            'items' => $items,
            'emptyMessage' => 'Niente da approvare al momento.',
        ]);
    }

    private function buildAgentPanel(): array
    {
        $totalLeads = $this->leads->count();
        $qualifiedLeads = $totalLeads - ($this->leads->countGroupedBy('stage')['nuovo'] ?? 0);
        $duplicates = count($this->leads->findDuplicateCandidates(200));
        $missingDocs = $this->documentRequests->count(['completeness_status' => 'mancante']);
        $staleLeads = $this->leads->countStale(date('Y-m-d H:i:s', strtotime('-48 hours')));
        $refundedAmount = $this->refunds->sumAcceptedAmount();

        return $this->agentPanel->toData([
            'title' => 'AI Orchestrator',
            'subtitle' => 'Agenti al lavoro',
            'note' => 'Solo Lead Intake, Follow-up commerciale, Document Manager e Amministrazione hanno oggi una rilevazione reale dietro (vedi la coda "Da approvare"): gli altri sono ancora manuali, il livello indica il traguardo, non lo stato attuale.',
            'agents' => [
                ['name' => 'Lead Intake e Data Quality', 'summary' => "{$duplicates} possibili duplicati rilevati", 'level' => 'assistito'],
                ['name' => 'Agente di qualificazione', 'summary' => "{$qualifiedLeads} di {$totalLeads} contatti qualificati", 'level' => 'manuale'],
                ['name' => 'Follow-up commerciale', 'summary' => "{$staleLeads} contatti da richiamare", 'level' => 'assistito'],
                ['name' => 'Document Manager', 'summary' => "{$missingDocs} documenti mancanti", 'level' => 'assistito'],
                ['name' => 'Assistente legale operativo', 'summary' => 'Nessuna bozza automatica ancora', 'level' => 'manuale'],
                ['name' => 'ConciliaWeb Assistant', 'summary' => 'Integrazione col portale non ancora collegata', 'level' => 'manuale'],
                ['name' => 'Amministrazione e rimborsi', 'summary' => number_format($refundedAmount, 0, ',', '.') . ' € rimborsati', 'level' => 'assistito'],
                ['name' => 'Marketing e Conversion Intelligence', 'summary' => 'Tracciamento campagne non ancora collegato', 'level' => 'manuale'],
                ['name' => 'Management Reporter', 'summary' => 'Report e cruscotto calcolati in tempo reale', 'level' => 'autonomo'],
            ],
        ]);
    }
}
