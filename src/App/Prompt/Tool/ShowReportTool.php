<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\CaseFileRepository;
use App\Repository\CommunicationRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\LeadRepository;
use App\Repository\TaskRepository;
use App\View\Component\DataTableComponent;
use App\View\Component\StatBoxComponent;

/**
 * Riepilogo numerico multi-pacchetto (agente "Management Reporter" del
 * brief Assilevi, punto 6 dell'MVP consigliato) - bespoke apposta, non
 * rappresenta UNA entita' ma incrocia contatori di piu' repository.
 */
final class ShowReportTool implements PromptToolInterface
{
    private LeadRepository $leads;
    private CaseFileRepository $cases;
    private DocumentRequestRepository $documentRequests;
    private CommunicationRepository $communications;
    private TaskRepository $tasks;
    private StatBoxComponent $statBox;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->leads = $container->get(LeadRepository::class);
        $this->cases = $container->get(CaseFileRepository::class);
        $this->documentRequests = $container->get(DocumentRequestRepository::class);
        $this->communications = $container->get(CommunicationRepository::class);
        $this->tasks = $container->get(TaskRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'show_report';
    }

    public function description(): string
    {
        return "Mostra il report riepilogativo: pratiche, nuovi contatti, comunicazioni, "
            . "attivita' in scadenza e documenti mancanti.";
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

        return [
            $this->stat('Pratiche aperte', (string) $this->cases->count(), 'attive, escluse quelle eliminate'),
            $this->stat('Nuovi contatti', (string) $this->leads->countCreatedSince($weekAgo), 'ultimi 7 giorni'),
            $this->stat('Comunicazioni inviate', (string) $this->communications->countSentSince($weekAgo), 'ultimi 7 giorni'),
            $this->stat('Attivita\' in scadenza', (string) $this->tasks->countDueBy($threeDaysAhead), 'non completate, entro 3 giorni'),
            $this->stat('Duplicati contatti sospetti', (string) count($this->leads->findDuplicateCandidates(200)), 'da rivedere in "Duplicati contatti"'),
            $this->breakdown('Pratiche per stato', $this->cases->countGroupedBy('stage')),
            $this->breakdown('Documenti per stato completezza', $this->documentRequests->countGroupedBy('completeness_status')),
        ];
    }

    private function stat(string $label, string $value, string $comparison): array
    {
        return $this->statBox->toData([
            'label' => $label,
            'value' => $value,
            'comparison' => $comparison,
            'sparkline' => [3, 3, 4, 4, 4, 5, (int) $value],
        ]);
    }

    /** @param array<string,int> $counts */
    private function breakdown(string $title, array $counts): array
    {
        $rows = [];
        foreach ($counts as $label => $total) {
            $rows[] = ['label' => $label, 'total' => $total];
        }

        return $this->dataTable->toData([
            'title' => $title,
            'columns' => [
                ['key' => 'label', 'label' => 'Stato', 'badge' => true],
                ['key' => 'total', 'label' => 'Conteggio'],
            ],
            'rows' => $rows,
            'emptyMessage' => 'Nessun dato.',
        ]);
    }
}
