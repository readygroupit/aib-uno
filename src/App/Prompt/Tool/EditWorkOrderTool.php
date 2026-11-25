<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\WorkOrder;
use App\Repository\WorkOrderRepository;
use App\View\Component\WorkOrderSummaryComponent;
use App\Work\WorkOrderOverviewService;

final class EditWorkOrderTool extends AbstractEditEntityTool
{
    private WorkOrderOverviewService $overview;
    private WorkOrderSummaryComponent $summary;

    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->overview = $container->get(WorkOrderOverviewService::class);
        $this->summary = $container->get(WorkOrderSummaryComponent::class);
    }

    protected function repositoryClass(): string
    {
        return WorkOrderRepository::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function entityTag(): string
    {
        return 'work_order';
    }

    protected function baseUrl(): string
    {
        return '/commesse';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova commessa' : 'Modifica commessa';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Dati della commessa';
    }

    protected function searchColumns(): array
    {
        return ['title'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Commessa'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'dueDate', 'label' => 'Consegna'],
        ];
    }

    protected function systemManagedFields(): array
    {
        return ['last_reminder_at', 'last_activity_at'];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof WorkOrder) {
            return [];
        }

        $detail = $this->overview->detail($model);
        $card = $detail['card'];
        $base = '/commesse/' . $model->id;

        $actions = [];
        $process = $this->container->get(\App\Work\ProcessService::class);
        $hasProcess = $process->hasProcess($model->workOrderTypeId !== null ? (int) $model->workOrderTypeId : null);
        if (in_array('quotes', \App\Package\PackageCatalogService::installedNames(), true) && ($model->quoteId !== null || $model->isCommercial())) {
            $actions[] = $model->quoteId !== null
                ? ['label' => 'Preventivo', 'href' => '/preventivi/' . $model->quoteId, 'variant' => 'secondary', 'icon' => 'file-invoice']
                : ['label' => 'Prepara il preventivo', 'action' => "{$base}/preventivo", 'variant' => 'primary', 'icon' => 'file-invoice'];
        }
        if ($model->isCommercial()) {
            $actions[] = ['label' => 'Non acquisito', 'action' => "{$base}/non-acquisita", 'variant' => 'ghost', 'ask' => 'Perche\' non e\' andata?', 'placeholder' => 'Es. ha scelto un\'altra agenzia, budget troppo basso'];
        }
        if ($model->stage === 'in attesa del cliente') {
            $actions[] = ['label' => 'Ho sollecitato', 'action' => "{$base}/sollecitato", 'variant' => 'secondary', 'icon' => 'bell'];
            $actions[] = ['label' => 'Il cliente ha risposto', 'action' => "{$base}/riprendi", 'variant' => 'primary', 'icon' => 'check'];
        } elseif ($model->isActive() && !$model->isCommercial()) {
            $actions[] = ['label' => 'Aspettiamo il cliente', 'action' => "{$base}/attesa", 'variant' => 'secondary', 'ask' => 'Cosa aspettiamo dal cliente?', 'icon' => 'clock'];
            if ($model->stage === 'in pausa') {
                $actions[] = ['label' => 'Riprendi', 'action' => "{$base}/riprendi", 'variant' => 'secondary'];
            } else {
                $actions[] = ['label' => 'Metti in pausa', 'action' => "{$base}/pausa", 'variant' => 'ghost'];
            }
        }
        if ($model->isActive() && !$model->isCommercial()) {
            $allDone = $card['progress']['total'] > 0 && $card['progress']['done'] === $card['progress']['total'];
            $actions[] = [
                'label' => 'Consegnata',
                'action' => "{$base}/consegnata",
                'variant' => $allDone ? 'primary' : 'secondary',
                'confirm' => $allDone ? null : "Ci sono ancora fasi da fare. Segnare comunque la commessa come consegnata?",
                'icon' => 'circle-check',
            ];
        }

        $appointments = null;
        if (in_array('appointments', \App\Package\PackageCatalogService::installedNames(), true)) {
            $calendar = $this->container->get(\App\Calendar\CalendarOverviewService::class);
            $appointments = $calendar->shortList($this->container->get(\App\Repository\AppointmentRepository::class)->findForEntity('work_orders', (int) $model->id));
        }

        $components = [$this->summary->toData([
            'url' => $base,
            'appointments' => $appointments,
            'addAppointmentHref' => $appointments !== null
                ? '/appuntamenti/nuovo?entity_type=work_orders&entity_id=' . $model->id . '&customer_id=' . $model->customerId . '&title=' . rawurlencode((string) $model->title)
                : null,
            'card' => $card,
            'steps' => $detail['steps'],
            'hours' => $detail['hours'],
            'expenses' => $detail['expenses'],
            'newExpenseHref' => $detail['newExpenseHref'],
            'supplier' => $detail['supplier'],
            'notes' => $detail['notes'],
            'actions' => $actions,
            'process' => $hasProcess ? $this->container->get(\App\Work\ProcessOverviewService::class)->forOrder($model, $detail['steps']) : null,
            'addStepHref' => '/attivita/nuovo?entity_type=work_orders&entity_id=' . $model->id
                . ($model->ownerUserId !== null ? '&assigned_user_id=' . $model->ownerUserId : '')
                . '&position=' . ($card['progress']['total'] + 1),
        ])];
        if (\App\Documents\DocumentService::isInstalled() && $this->auth->hasPermission('documents.view')
            && ($documents = $this->container->get(\App\Documents\DocumentService::class)->summaryFor((int) $model->id, $model->customerId)) !== null) {
            $components[] = $documents;
        }
        if ($model->customerId !== null && \App\Knowledge\KnowledgeService::isInstalled() && $this->auth->hasPermission('client_knowledge.view')) {
            $components[] = $this->container->get(\App\Knowledge\KnowledgeService::class)->forCustomer((int) $model->customerId, (int) $model->id);
        }

        return $components;
    }

    /**
     * Il progetto: in testa per chi e', a che punto e', le azioni e cosa
     * fare adesso; sotto le schede "Fasi" (si apre per prima), "Squadra e
     * date", "Consegne", "Cliente e file" e "Dettagli" (il form). Le
     * schede vuote non ci sono (es. un progetto senza processo).
     */
    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        if (!$model instanceof WorkOrder || $model->id === null) {
            return parent::buildPage($model, $message, $messageType, $fieldErrors);
        }
        $detail = $this->detailComponents($model);
        $data = array_shift($detail);
        $process = $data['process'];
        $card = $data['card'];

        $head = $data;
        $head['guide'] = [
            'title' => 'Il progetto',
            'text' => "In alto per chi e', a che punto e' e le azioni del momento (preventivo, attesa del cliente, consegna). Il riquadro colorato dice la cosa da fare adesso.",
            'steps' => [
                'Fasi: le cose da fare in ordine; ognuno vede le sue nella sua giornata.',
                'Squadra e date: chi ci lavora, le date chiave da fissare, gli appuntamenti.',
                'Consegne: cosa riceve il cliente e le modifiche che ha chiesto.',
                'Cliente e file: diario, profilo del cliente e documenti del progetto.',
            ],
            'tip' => 'Righe, fasi, persone e date si aprono nel pannello laterale: la pagina resta sotto.',
        ];

        $open = max(0, (int) $card['progress']['total'] - (int) $card['progress']['done']);
        $tabs = [[
            'key' => 'fasi',
            'label' => 'Fasi',
            'icon' => 'list-check',
            'color' => 'teal',
            'count' => $open > 0 ? $open : null,
            'components' => [[
                'type' => 'wo-steps',
                'process' => $process,
                'steps' => $data['steps'],
                'hours' => $data['hours'],
                'expenses' => $data['expenses'],
                'newExpenseHref' => $data['newExpenseHref'],
                'addStepHref' => $data['addStepHref'],
                'guide' => [
                    'text' => "Le fasi del progetto, nell'ordine. Ognuna e' di chi ha il ruolo: quando una e' fatta, parte la successiva e chi la deve fare riceve l'avviso.",
                    'steps' => ['Spunta una fase quando e\' fatta (o aprila per i dettagli).', 'Approvazioni, briefing e raccolte hanno i loro pulsanti sulla fase.', '"+ Fase" aggiunge un lavoro solo per questo progetto.'],
                    'tip' => 'Previsto e impiegato confrontano le ore stimate con quelle segnate.',
                ],
            ]],
        ]];

        $teamComponents = [];
        $missingRoles = 0;
        if ($process !== null) {
            $missingRoles = count($process['team']['missing']);
            $teamComponents[] = ['type' => 'wo-team', 'team' => $process['team'], 'guide' => "Chi lavora al progetto e con che ruolo. \"Chi?\" e' un ruolo ancora da assegnare: finche' manca, le sue fasi non hanno nessuno."];
            if ($process['milestones'] !== []) {
                $teamComponents[] = ['type' => 'wo-milestones', 'milestones' => $process['milestones'], 'guide' => "Le date chiave del progetto. \"Fissa la data\" la mette in calendario; dove c'e' l'email, prima di mandarla al cliente la vedi e la puoi cambiare."];
            }
        }
        if ($data['appointments'] !== null) {
            $teamComponents[] = ['type' => 'wo-appointments', 'items' => $data['appointments'], 'addHref' => $data['addAppointmentHref'], 'guide' => 'Incontri, shooting e chiamate del progetto, con il calendario.'];
        }
        if ($teamComponents !== []) {
            $tabs[] = ['key' => 'squadra', 'label' => 'Squadra e date', 'short' => 'Squadra', 'icon' => 'users', 'color' => 'lilac', 'count' => $missingRoles > 0 ? $missingRoles : null, 'components' => $teamComponents];
        }

        if ($process !== null) {
            $pending = count(array_filter($process['deliverables']['items'], static fn (array $i) => !$i['done']));
            $tabs[] = [
                'key' => 'consegne',
                'label' => 'Consegne',
                'icon' => 'box-open',
                'color' => 'moss',
                'count' => $pending > 0 ? $pending : null,
                'components' => [
                    ['type' => 'wo-deliverables', 'deliverables' => $process['deliverables'], 'guide' => "Cosa riceve il cliente. Spunta una voce quando e' pronta: alla consegna devono esserci tutte."],
                    ['type' => 'wo-changes', 'changes' => $process['changes'], 'guide' => "Le modifiche che il cliente chiede dopo la presentazione. Per ognuna si dice se e' compresa o extra (da quotare)."],
                ],
            ];
        }

        if ($detail !== []) {
            $tabs[] = ['key' => 'cliente', 'label' => 'Cliente e file', 'short' => 'Cliente', 'icon' => 'folder', 'color' => 'brass', 'components' => $detail];
        }

        return $this->tabbedPage($model, $head, $tabs, $message, $messageType, $fieldErrors, ['short' => 'Dati']);
    }

    public function name(): string
    {
        return 'edit_work_order';
    }

    public function description(): string
    {
        return "Apre una commessa (con fasi, avanzamento, attese del cliente), cercata per id o titolo.";
    }

    public function requiredPermission(): ?string
    {
        return 'work_orders.edit';
    }
}
