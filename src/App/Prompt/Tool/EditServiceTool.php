<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\WorkOrderType;
use App\Package\PackageCatalogService;
use App\Repository\ServiceRepository;
use App\Repository\WorkOrderTypeRepository;

/**
 * Un servizio del listino e, sotto, il suo processo di lavoro (fasi, ruoli,
 * date chiave, cose da consegnare): il posto unico dove si definisce cosa
 * vende l'agenzia e come lo fa.
 */
final class EditServiceTool extends AbstractEditEntityTool
{
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
    }

    protected function formTitleBelowDetail(): ?string
    {
        return in_array('work_orders', PackageCatalogService::installedNames(), true) ? 'Dati di vendita' : null;
    }

    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        return $this->servicePage($model, $message, $messageType, $fieldErrors);
    }

    /**
     * La pagina del servizio: in testa nome e numeri, sotto tre schede,
     * "Il servizio" e "Vendita" (le due sezioni del form, un solo "Salva")
     * e "Processo di lavoro". $tab: la scheda da aprire (es. 'processo'
     * dopo aver salvato una fase); senza, quella dell'indirizzo (#vendita).
     */
    public function servicePage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = [], ?string $tab = null): array
    {
        if ($model->id === null || !in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            return parent::buildPage($model, $message, $messageType, $fieldErrors);
        }

        $type = $this->container->get(WorkOrderTypeRepository::class)->findByService((int) $model->id);
        $process = $this->detailComponents($model);
        $steps = $type instanceof WorkOrderType ? $process[0] : null;

        $form = $this->buildForm($model, $fieldErrors !== [] ? $message : null, $fieldErrors !== [] ? $messageType : null, $fieldErrors);
        $form['title'] = '';
        $form['collapsed'] = false;
        $form['tabsExternal'] = true;
        $sectionIndex = [];
        foreach ($form['sections'] as $i => $section) {
            $sectionIndex[$section['key'] ?? $i] = $i;
        }

        $tabs = [[
            'key' => 'servizio',
            'label' => 'Il servizio',
            'icon' => 'tag',
            'color' => 'lilac',
            'formSection' => $sectionIndex['servizio'] ?? 0,
            // Sotto i dati del servizio, il resto del processo (cartelle, durata).
            'components' => $type instanceof WorkOrderType
                ? [$this->container->get(EditWorkOrderTypeTool::class)->processSettingsForm($type)]
                : [],
        ]];
        if (isset($sectionIndex['vendita'])) {
            $tabs[] = [
                'key' => 'vendita',
                'label' => 'Vendita',
                'icon' => 'euro-sign',
                'color' => 'brass',
                'formSection' => $sectionIndex['vendita'],
            ];
        }
        $tabs[] = [
            'key' => 'processo',
            'label' => 'Processo di lavoro',
            'short' => 'Processo',
            'icon' => 'route',
            'color' => 'teal',
            'count' => $steps !== null ? count($steps['steps']) : null,
            'components' => $process,
        ];

        $components = [];
        if ($message !== null && $fieldErrors === []) {
            $components[] = ['type' => 'notice', 'tone' => $messageType === 'error' ? 'error' : 'success', 'text' => $message];
        }
        $components[] = $this->header($model, $steps);
        $components[] = ['type' => 'page-tabs', 'label' => 'Sezioni del servizio', 'selected' => $tab, 'form' => $form, 'tabs' => $tabs];

        return $components;
    }

    /** In testa alla pagina: nome, categoria, prezzo indicativo, i numeri del processo. */
    private function header(AbstractModel $model, ?array $steps): array
    {
        $price = (float) $model->price > 0
            ? 'indicativo ' . \App\Support\ItalianFormat::euro((float) $model->price) . ($model->billingType === 'ricorrente' ? ' ' . (string) $model->recurrence : '')
            : 'prezzo nel preventivo';
        $facts = [];
        if ((string) $model->category !== '') {
            $facts[] = ['icon' => 'tag', 'color' => 'lilac', 'text' => (string) $model->category];
        }
        $facts[] = ['icon' => 'euro-sign', 'color' => 'brass', 'text' => $price];
        if ($model->stage === 'non disponibile') {
            $facts[] = ['icon' => 'flag', 'color' => 'danger', 'text' => 'non disponibile'];
        }
        if ($steps !== null) {
            if (count($steps['roles']) > 0) {
                $facts[] = ['icon' => 'users', 'color' => 'lilac', 'text' => count($steps['roles']) . ' ruoli'];
            }
            $facts[] = ['icon' => 'list-check', 'color' => 'teal', 'text' => count($steps['steps']) . ' fasi'];
            if (count($steps['milestones']) > 0) {
                $facts[] = ['icon' => 'calendar-days', 'color' => 'brass', 'text' => count($steps['milestones']) . ' date chiave'];
            }
        } else {
            $facts[] = ['icon' => 'route', 'color' => 'teal', 'text' => 'processo da creare'];
        }

        return [
            'type' => 'service-header',
            'kicker' => 'Servizio del listino',
            'name' => (string) $model->name,
            'facts' => $facts,
            'action' => $steps !== null ? ['label' => 'Nuovo progetto con questo servizio', 'href' => $steps['newOrderHref']] : null,
            'guide' => [
                'title' => 'La pagina del servizio',
                'text' => "Qui si definisce un servizio che l'agenzia vende e come si lavora per farlo. Il processo e' la sua ricetta: quando un cliente compra il servizio, il progetto nasce con le fasi gia' pronte e assegnate ai ruoli, e l'assistente avvisa ognuno quando tocca a lui.",
                'steps' => [
                    'Il servizio: nome, descrizione, chi lo fa; sotto, le cartelle sul Drive e la durata del processo.',
                    'Vendita: prezzo indicativo, costi, come si fattura. Un solo "Salva" per Il servizio e Vendita.',
                    'Processo di lavoro: chi ci lavora, date chiave, fasi e cosa si consegna. Ogni parte si apre dal suo titolo.',
                ],
                'tip' => '"Nuovo progetto con questo servizio" apre un progetto con le fasi gia\' pronte.',
            ],
        ];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if ($model->id === null || !in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            return [];
        }
        $types = $this->container->get(WorkOrderTypeRepository::class);
        $type = $types->findByService((int) $model->id);
        if ($type instanceof WorkOrderType) {
            return $this->container->get(EditWorkOrderTypeTool::class)->processComponents($type);
        }

        return [[
            'type' => 'service-process-missing',
            'service' => (string) $model->name,
            'createAction' => '/servizi/' . $model->id . '/processo',
            'existing' => array_map(static fn (WorkOrderType $t) => ['id' => (int) $t->id, 'name' => (string) $t->name], $types->findWithoutService()),
        ]];
    }

    protected function repositoryClass(): string
    {
        return ServiceRepository::class;
    }

    protected function packageName(): string
    {
        return 'services';
    }

    protected function entityTag(): string
    {
        return 'service';
    }

    protected function baseUrl(): string
    {
        return '/servizi';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo servizio' : 'Modifica servizio';
    }

    protected function searchColumns(): array
    {
        return ['name', 'category', 'description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'category', 'label' => 'Categoria'],
            ['key' => 'price', 'label' => 'Prezzo'],
        ];
    }

    public function name(): string
    {
        return 'edit_service';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un servizio, cercato per id o per nome/categoria/descrizione. "
            . "Usalo quando l'utente chiede di aprire o modificare uno specifico servizio.";
    }

    public function requiredPermission(): ?string
    {
        return 'services.edit';
    }
}
