<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\CaseFileRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\LeadRepository;
use App\Repository\RefundRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\View\Component\AgentFeedComponent;
use App\View\Component\DashboardHeaderComponent;
use App\View\Component\FunnelComponent;
use App\View\Component\StatBoxComponent;

/**
 * Cruscotto della home: intestazione (chi sei, che giorno e', su quale
 * intervallo stai guardando), contatori cliccabili, il ciclo di vita
 * completo lead -> rimborso come imbuto e, in cima, i messaggi degli
 * agenti all'operatore (vedi AgentService - ambiente dimostrativo) -
 * traduzione della struttura del brief Assilevi (6 Agenti AI, 7 Flusso
 * operativo, 10 Livelli di automazione) in sezioni di schermata.
 */
final class ShowHomeDashboardTool implements PromptToolInterface
{
    private LeadRepository $leads;
    private CaseFileRepository $cases;
    private DocumentRequestRepository $documentRequests;
    private TaskRepository $tasks;
    private RefundRepository $refunds;
    private UserRepository $users;
    private AuthService $auth;
    private StatBoxComponent $statBox;
    private DashboardHeaderComponent $header;
    private FunnelComponent $funnel;
    private AgentFeedComponent $agentFeed;

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
        $this->auth = $container->get(AuthService::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->header = $container->get(DashboardHeaderComponent::class);
        $this->funnel = $container->get(FunnelComponent::class);
        $this->agentFeed = $container->get(AgentFeedComponent::class);
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
            ...$this->buildAgentFeed(),
            ...$this->buildStatCards($range['days']),
            $this->buildFunnel(),
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

        $rangeTabs = [];
        foreach (self::RANGES as $key => $def) {
            $rangeTabs[] = ['label' => $def['label'], 'active' => $key === $rangeKey, 'href' => '/?range=' . $key];
        }

        return $this->header->toData([
            'dateLabel' => mb_strtoupper($dateLabel),
            'greeting' => 'Buongiorno' . ($user?->firstName !== null ? ", {$user->firstName}" : ''),
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
     * I messaggi degli agenti (vedi AgentService) in cima alla home, solo
     * per chi puo' vederli. Sostituisce la vecchia coda "Da approvare" e il
     * pannello agenti: stessa idea (l'operatore approva, gli agenti
     * lavorano), ma detta dagli agenti stessi invece che da una tabella.
     *
     * @return list<array<string, mixed>>
     */
    private function buildAgentFeed(): array
    {
        return $this->auth->hasPermission('agents.view') ? [$this->agentFeed->toData(['limit' => 5])] : [];
    }
}
