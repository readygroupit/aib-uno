<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\LeadRepository;
use App\View\Component\DataTableComponent;

/**
 * Coppie di contatti che sembrano duplicati (stessa email/telefono/volo),
 * non ancora collegati - vedi LeadRepository::findDuplicateCandidates() e
 * il commento li' sul perche' e' solo un suggerimento, mai un merge
 * automatico (brief Assilevi). Bespoke: non e' una lista di UNA entita'
 * (AbstractListEntityTool), ogni riga e' una COPPIA.
 */
final class ListLeadDuplicatesTool implements PromptToolInterface
{
    private LeadRepository $leads;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->leads = $container->get(LeadRepository::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'list_lead_duplicates';
    }

    public function description(): string
    {
        return "Mostra le coppie di contatti che sembrano duplicati (stessa email, telefono o volo+data) "
            . "e non sono ancora collegati.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function menuLabel(): ?string
    {
        return 'Duplicati contatti';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function menuCount(): ?string
    {
        return (string) count($this->leads->findDuplicateCandidates(200));
    }

    public function requiredPermission(): ?string
    {
        return 'leads.manage';
    }

    public function triggers(): array
    {
        return ['duplicati', 'duplicati contatti', 'contatti duplicati', 'pratiche familiari'];
    }

    public function execute(array $input): array
    {
        $candidates = $this->leads->findDuplicateCandidates(50);

        $rows = array_map(static fn (array $c) => [
            'id' => $c['bId'],
            'aLabel' => $c['aLabel'] . ' (#' . $c['aId'] . ')',
            'bLabel' => $c['bLabel'] . ' (#' . $c['bId'] . ')',
            'reason' => $c['reason'],
        ], $candidates);

        $table = $this->dataTable->toData([
            'entity' => 'lead_duplicate',
            'url' => '/contatti/duplicati',
            'title' => 'Possibili duplicati',
            'columns' => [
                ['key' => 'aLabel', 'label' => 'Contatto piu\' vecchio'],
                ['key' => 'bLabel', 'label' => 'Contatto piu\' recente'],
                ['key' => 'reason', 'label' => 'Motivo'],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => 'Confronta e collega', 'href' => '/contatti/duplicati/{id}', 'permission' => 'leads.manage'],
            ],
            'emptyMessage' => 'Nessun duplicato sospetto al momento.',
        ]);

        return [$table];
    }
}
