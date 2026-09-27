<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\CaseFileRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\LeadRepository;
use App\Repository\TaskRepository;
use App\View\Component\StatBoxComponent;

/**
 * Cruscotto della home: contatori cliccabili invece di un elenco fisso -
 * si apre gia' sapendo cosa richiede attenzione, si clicca per il
 * dettaglio (vedi la nota dell'utente su "contatori, non per forza
 * liste"). Popola App\Controller\Index\IndexController::indexAction():
 * appena la home ha componenti reali, il prompt si sposta da solo in
 * sidebar (vedi hero.js, meccanismo gia' usato da ogni altra pagina).
 */
final class ShowHomeDashboardTool implements PromptToolInterface
{
    private LeadRepository $leads;
    private CaseFileRepository $cases;
    private DocumentRequestRepository $documentRequests;
    private TaskRepository $tasks;
    private StatBoxComponent $statBox;

    public function __construct(Container $container)
    {
        $this->leads = $container->get(LeadRepository::class);
        $this->cases = $container->get(CaseFileRepository::class);
        $this->documentRequests = $container->get(DocumentRequestRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
    }

    public function name(): string
    {
        return 'show_home_dashboard';
    }

    public function description(): string
    {
        return "Mostra il cruscotto iniziale con i contatori delle cose che richiedono attenzione.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
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

    public function triggers(): array
    {
        return [];
    }

    public function execute(array $input): array
    {
        $twoDaysAgo = date('Y-m-d H:i:s', strtotime('-48 hours'));
        $threeDaysAhead = date('Y-m-d H:i:s', strtotime('+3 days'));

        $weekStart = date('Y-m-d H:i:s', strtotime('-7 days'));
        $prevWeekStart = date('Y-m-d H:i:s', strtotime('-14 days'));
        $newLeadsThisWeek = $this->leads->countBetween('created_at', $weekStart, date('Y-m-d H:i:s'));
        $newLeadsPrevWeek = $this->leads->countBetween('created_at', $prevWeekStart, $weekStart);
        $delta = $newLeadsPrevWeek > 0
            ? round((($newLeadsThisWeek - $newLeadsPrevWeek) / $newLeadsPrevWeek) * 100)
            : null;

        return [
            $this->card(
                'Contatti da richiamare',
                (string) $this->leads->countStale($twoDaysAgo),
                'fermi da oltre 48 ore',
                '/contatti',
                'danger'
            ),
            $this->card(
                'Duplicati da rivedere',
                (string) count($this->leads->findDuplicateCandidates(200)),
                'possibili pratiche familiari',
                '/contatti/duplicati',
                'brass'
            ),
            $this->card(
                'Documenti mancanti',
                (string) $this->documentRequests->count(['completeness_status' => 'mancante']),
                'da sollecitare al cliente',
                '/documenti-richiesti',
                'lilac'
            ),
            $this->card(
                'Attivita\' in scadenza',
                (string) $this->tasks->countDueBy($threeDaysAhead),
                'non completate, entro 3 giorni',
                '/attivita',
                'lilac'
            ),
            $this->card(
                'Pratiche aperte',
                (string) $this->cases->count(),
                'attive, escluse quelle eliminate',
                '/pratiche',
                'lilac'
            ),
            $this->statBox->toData([
                'label' => 'Nuovi contatti',
                'value' => (string) $newLeadsThisWeek,
                'delta' => $delta !== null ? ($delta >= 0 ? "+{$delta}%" : "{$delta}%") : null,
                'deltaPositive' => $delta === null || $delta >= 0,
                'comparison' => 'ultimi 7 giorni vs 7 precedenti',
                'sparkline' => $this->leads->countPerDayLast7('created_at'),
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
        ]);
    }
}
