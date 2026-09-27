<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\LeadRepository;
use App\View\Component\DataTableComponent;
use App\View\Component\StatBoxComponent;

/**
 * Stessa forma di ListUsersTool: unica sorgente della schermata contatti,
 * richiamata sia da LeadsController (menu/route) sia dal prompt.
 */
final class ListLeadsTool implements PromptToolInterface
{
    private LeadRepository $leads;
    private StatBoxComponent $statBox;
    private DataTableComponent $dataTable;

    public function __construct(Container $container)
    {
        $this->leads = $container->get(LeadRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->dataTable = $container->get(DataTableComponent::class);
    }

    public function name(): string
    {
        return 'list_leads';
    }

    public function description(): string
    {
        return "Mostra l'elenco dei contatti (lead) con statistica totale.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'integer', 'description' => 'Numero di pagina, default 1'],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'Contatti';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function menuCount(): ?string
    {
        return (string) $this->leads->count();
    }

    public function requiredPermission(): ?string
    {
        return 'leads.manage';
    }

    public function triggers(): array
    {
        return ['contatti', 'lista contatti', 'elenco contatti', 'lead', 'leads'];
    }

    public function execute(array $input): array
    {
        $page = max(1, (int) ($input['page'] ?? 1));
        $result = $this->leads->paginate($page, 10, [], 'id DESC');
        $rows = array_map(static fn ($lead) => $lead->toDisplayArray(), $result['items']);

        $stats = $this->statBox->toData([
            'label' => 'Totale contatti',
            'value' => (string) $result['total'],
            'comparison' => 'attivi, esclusi quelli eliminati',
            'sparkline' => [3, 3, 4, 4, 4, 5, $result['total']],
        ]);

        $table = $this->dataTable->toData([
            'entity' => 'lead',
            'url' => '/contatti',
            'title' => 'Contatti',
            'createHref' => '/contatti/nuovo',
            'createLabel' => 'Nuovo contatto',
            'createPermission' => 'leads.manage',
            'columns' => [
                ['key' => 'firstName', 'label' => 'Nome'],
                ['key' => 'lastName', 'label' => 'Cognome'],
                ['key' => 'email', 'label' => 'Email'],
                ['key' => 'phone', 'label' => 'Telefono'],
                // Utile solo per chi usa questi campi (es. reclami di
                // viaggio) - per chi non li ha installati e' semplicemente
                // vuota, non e' un problema mostrarla comunque.
                ['key' => 'disserviceType', 'label' => 'Disservizio'],
                ['key' => 'stage', 'label' => 'Stato'],
                ['key' => 'priority', 'label' => 'Priorita'],
            ],
            'rows' => $rows,
            'actions' => [
                ['label' => 'Modifica', 'href' => '/contatti/{id}', 'permission' => 'leads.manage'],
            ],
            'page' => $result['page'],
            'perPage' => $result['perPage'],
            'total' => $result['total'],
        ]);

        return [$stats, $table];
    }
}
