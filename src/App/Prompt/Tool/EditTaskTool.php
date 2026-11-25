<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\Task;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Support\ItalianFormat as F;
use App\View\Component\TaskSummaryComponent;
use App\Work\WorkOrderOverviewService;
use App\Work\WorkOrderService;

final class EditTaskTool extends AbstractEditEntityTool
{
    private TaskSummaryComponent $summary;
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->summary = $container->get(TaskSummaryComponent::class);
    }

    protected function repositoryClass(): string
    {
        return TaskRepository::class;
    }

    protected function packageName(): string
    {
        return 'tasks';
    }

    protected function entityTag(): string
    {
        return 'task';
    }

    protected function baseUrl(): string
    {
        return '/attivita';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? "Nuova attivita'" : "Modifica attivita'";
    }

    protected function formTitleBelowDetail(): ?string
    {
        return "Dettagli dell'attivita'";
    }

    protected function searchColumns(): array
    {
        return ['title', 'description'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'plannedDate', 'label' => 'Da fare il'],
            ['key' => 'stage', 'label' => 'Stato'],
        ];
    }

    /** Esito, data dell'esito e note sulle voci si scrivono dalla scheda "Da fare", non dal form. */
    protected function systemManagedFields(): array
    {
        return [...parent::systemManagedFields(), 'outcome', 'outcome_date', 'item_notes'];
    }

    /** A cosa e' collegata (commessa, cliente) arriva dal pulsante che la crea, non si sceglie a mano. */
    protected function hiddenFields(): array
    {
        return ['entity_type', 'entity_id', 'source_step_id'];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof Task) {
            return [];
        }

        $context = null;
        $installed = PackageCatalogService::installedNames();
        if ($model->entityType === WorkOrderService::ENTITY && $model->entityId !== null && in_array('work_orders', $installed, true)) {
            $order = $this->container->get(WorkOrderRepository::class)->find((int) $model->entityId);
            if ($order !== null) {
                $context = ['label' => 'Commessa: ' . $order->title, 'href' => '/commesse/' . $order->id];
            }
        } elseif ($model->entityType === 'customers' && $model->entityId !== null && in_array('customers', $installed, true)) {
            $customer = $this->container->get(CustomerRepository::class)->find((int) $model->entityId);
            if ($customer !== null) {
                $context = ['label' => 'Cliente: ' . $customer->displayName(), 'href' => '/clienti/' . $customer->id];
            }
        }

        $who = null;
        if ($model->assignedUserId !== null) {
            $user = $this->container->get(UserRepository::class)->find((int) $model->assignedUserId);
            $who = $user !== null ? trim("{$user->firstName} {$user->lastName}") : null;
        }

        $today = date('Y-m-d');
        $day = $model->day();
        $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $model->checklist)), static fn (string $l) => $l !== ''));

        // Fase del processo: briefing e raccolta informazioni hanno le risposte
        // sotto ogni voce; le fasi del preventivo mostrano cosa ha chiesto il cliente.
        $step = $model->sourceStepId !== null && in_array('work_orders', $installed, true)
            ? $this->container->get(\App\Repository\WorkOrderTypeStepRepository::class)->find((int) $model->sourceStepId)
            : null;
        $knowledge = \App\Knowledge\KnowledgeService::isInstalled() ? $this->container->get(\App\Knowledge\KnowledgeService::class) : null;
        $guided = $step !== null && $knowledge !== null ? $knowledge->guidedTaskData($model) : null;
        $brief = null;
        if ($knowledge !== null && $step !== null && $step->completesOn !== null && str_starts_with((string) $step->completesOn, 'preventivo')
            && $model->entityType === WorkOrderService::ENTITY) {
            $brief = $knowledge->quoteBrief((int) $model->entityId);
        }

        // Fasi che si chiudono da sole con il preventivo: il pulsante per
        // farlo e' qui (non solo nel progetto), e "Segna fatta" non serve.
        $actions = [];
        $autoClose = null;
        if ($step !== null && $step->completesOn !== null && $model->entityType === WorkOrderService::ENTITY
            && in_array('quotes', $installed, true)) {
            $autoClose = self::AUTO_CLOSE[$step->completesOn] ?? "Si chiude da sola ({$step->completesOn}).";
            $order = $this->container->get(WorkOrderRepository::class)->find((int) $model->entityId);
            if ($order !== null && $order->quoteId !== null && $this->auth->hasPermission('quotes.view')) {
                $actions[] = ['label' => 'Apri il preventivo', 'href' => '/preventivi/' . $order->quoteId, 'icon' => 'file-invoice'];
            } elseif ($order !== null && $order->quoteId === null && $step->completesOn === \App\Work\ProcessService::QUOTE_DRAFTED
                && $this->auth->hasPermission('quotes.create')) {
                $actions[] = ['label' => 'Prepara il preventivo', 'action' => '/commesse/' . $order->id . '/preventivo', 'icon' => 'file-invoice'];
            }
        }

        // "Comporre la squadra": i ruoli con le persone, si sceglie qui.
        $team = null;
        if ($step !== null && $step->kind === \App\Work\ProcessService::KIND_TEAM && $model->entityType === WorkOrderService::ENTITY) {
            $teamOrder = $this->container->get(WorkOrderRepository::class)->find((int) $model->entityId);
            $team = $teamOrder !== null ? $this->container->get(\App\Work\ProcessOverviewService::class)->teamEditor($teamOrder) : null;
        }

        // Quello che la fase chiede si fa qui (data, approvazione, consegna...).
        $tools = $step !== null && $model->entityType === WorkOrderService::ENTITY ? $this->stepTools($model, $step, $installed) : [];
        if (isset($tools['autoClose'])) {
            $autoClose = $tools['autoClose'];
        }

        // Le voci: spunta, testo e (nelle fasi guidate) la risposta sotto.
        $answers = $guided['answers'] ?? [];
        $itemNotes = \App\Work\TaskWorkService::itemNotes($model);
        $work = $model->entityType === WorkOrderService::ENTITY ? $this->workArea($model, $step) : [];
        $items = [];
        foreach ($lines as $i => $line) {
            $items[] = [
                'index' => $i,
                'text' => trim((string) preg_replace('/^\[x\]\s*/i', '', $line)),
                'done' => str_starts_with(strtolower($line), '[x]'),
                'answer' => $guided !== null ? ($answers[$i] ?? '') : null,
                // Una nota per voce (es. i font scelti): non nelle fasi guidate, che hanno la risposta.
                'note' => $guided === null && $model->entityType === WorkOrderService::ENTITY ? ($itemNotes[$i] ?? '') : null,
            ];
        }
        $extras = [];
        if ($guided !== null) {
            foreach (array_slice($guided['questions'], $guided['checklistCount'], null, true) as $i => $question) {
                $extras[] = ['index' => $i, 'text' => $question, 'answer' => $answers[$i] ?? ''];
            }
        }

        $components = [$this->summary->toData([
            'url' => '/attivita/' . $model->id,
            'title' => (string) $model->title,
            'done' => !$model->isOpen(),
            'context' => $context,
            'facts' => array_values(array_filter([
                $day !== null ? ['icon' => 'calendar', 'text' => 'Da fare il ' . F::shortDate($day, $today) . ' (' . F::relativeDays($day, $today) . ')'] : null,
                $model->dueAt !== null ? ['icon' => 'clock', 'text' => 'Scadenza ' . F::shortDate(substr($model->dueAt, 0, 10), $today) . ' ' . substr($model->dueAt, 11, 5)] : null,
                $who !== null ? ['icon' => 'user', 'text' => $who] : null,
                $model->estimatedMinutes ? ['icon' => 'clock', 'text' => 'Previsto ' . WorkOrderOverviewService::duration((int) $model->estimatedMinutes) . ($model->actualMinutes ? ', impiegato ' . WorkOrderOverviewService::duration((int) $model->actualMinutes) : '')] : null,
                $model->recurrence !== null ? ['icon' => 'arrows-rotate', 'text' => 'Si ripete ' . str_replace('ogni ', 'ogni ', $model->recurrence)] : null,
            ])),
            'tags' => array_values(array_filter([
                $model->priority === 'alta' ? ['label' => 'Priorita alta', 'tone' => 'danger'] : null,
                (int) $model->waitsForClient === 1 ? ['label' => 'Aspetta il cliente', 'tone' => 'warning'] : null,
            ])),
            'toggleAction' => '/attivita/' . $model->id . '/fatta',
            // Il pulsante principale e' in fondo a "Da fare".
            'primaryElsewhere' => true,
            // Data o approvazione da segnare: "Fatta" non c'e', si chiude da li'.
            'hideToggle' => $model->isOpen() && $step !== null && \App\Work\ProcessService::needsData($step->kind),
            // A chi tocca: si cambia da qui (la persona riceve l'avviso).
            'assign' => $this->auth->hasPermission('tasks.edit') ? [
                'current' => $model->assignedUserId,
                'role' => $step !== null && $step->roleId !== null ? ($this->container->get(\App\Repository\WorkOrderTypeRoleRepository::class)->find((int) $step->roleId)?->label) : null,
                'users' => array_map(static fn ($u) => ['id' => (int) $u->id, 'name' => trim("{$u->firstName} {$u->lastName}") ?: (string) $u->username], $this->container->get(UserRepository::class)->findAll([], 'first_name, last_name')),
                'action' => '/attivita/' . $model->id . '/assegna',
            ] : null,
        ]), [
            'type' => 'task-work',
            'title' => 'Da fare',
            'hint' => $model->description,
            'note' => $guided['hint'] ?? null,
            'items' => $items,
            'extras' => $extras,
            'withAnswers' => $guided !== null,
            'answerPlaceholder' => $guided['answerPlaceholder'] ?? null,
            'done' => !$model->isOpen(),
            'checkAction' => '/attivita/' . $model->id . '/punto',
            'saveAction' => $guided !== null ? '/attivita/' . $model->id . '/briefing' : null,
            'toggleAction' => '/attivita/' . $model->id . '/fatta',
            'completeLabel' => $guided['completeLabel'] ?? ($team !== null ? 'Squadra pronta' : 'Segna fatta'),
            'team' => $team,
            // A fase fatta il form resta (chiuso: "Cambia la data") e sopra si
            // vede cosa e' stato fatto (data fissata, email mandata).
            'embedForm' => $tools['form'] ?? null,
            'milestone' => $tools['milestone'] ?? null,
            'stepActions' => $model->isOpen() ? ($tools['actions'] ?? []) : [],
            'deliverables' => $tools['deliverables'] ?? null,
            'itemNoteAction' => '/attivita/' . $model->id . '/nota-voce',
            'outcome' => $work['outcome'] ?? null,
            'files' => $work['files'] ?? null,
            'clientApproval' => $work['clientApproval'] ?? null,
            'appointments' => $tools['appointments'] ?? null,
            'completeConfirm' => $team !== null && $team['missing'] !== []
                ? 'Manca ancora: ' . implode(', ', $team['missing']) . '. Segnare comunque la squadra come pronta?'
                : null,
            'actions' => $actions,
            'autoClose' => $model->isOpen() ? $autoClose : null,
            'guide' => $guided['guide'] ?? ($team !== null ? [
                'text' => "Scegli chi lavora al progetto, ruolo per ruolo. Chi entra riceve l'avviso e le fasi del suo ruolo passano a lui.",
                'steps' => ['Per ogni ruolo scegli la persona dal menu e premi "Aggiungi".', 'Un ruolo puo\' avere piu\' persone (es. gli aiuti).', 'La x accanto al nome la toglie: le sue fasi passano a chi resta nel ruolo.', '"Squadra pronta" chiude la fase.'],
                'tip' => 'Anche un esterno (es. un grafico freelance) si sceglie qui, se ha il suo profilo.',
            ] : null) ?? [
                'text' => "Qui c'e' cosa fare per questa attivita'. Spunta le voci man mano; quando hai finito, \"Segna fatta\".",
                'tip' => "Data, persona, stima e testo dell'attivita' si cambiano nella scheda \"Dettagli\".",
            ],
        ]];
        if ($brief !== null) {
            $components[] = $brief;
        }
        if ($guided !== null && $guided['known'] !== []) {
            $components[] = [
                'type' => 'quote-brief',
                'title' => "Cosa sappiamo gia' del cliente",
                'subtitle' => 'Dal profilo del cliente: evita di chiedere di nuovo quello che sai.',
                'items' => $guided['known'],
                'project' => null,
                'guide' => "Il profilo del cliente, aggiornato dai briefing e a mano dalla sua scheda. Lo usa anche l'assistente per scrivere e proporre.",
            ];
        }

        return $components;
    }

    /**
     * L'attivita': in testa titolo e stato, sotto due schede. "Da fare"
     * (si apre per prima): le voci da spuntare, con le risposte nelle fasi
     * guidate. "Dettagli": il form dell'attivita', gia' compilato.
     */
    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        if (!$model instanceof Task || $model->id === null) {
            return parent::buildPage($model, $message, $messageType, $fieldErrors);
        }
        $detail = $this->detailComponents($model);
        $summary = array_shift($detail);
        $work = $detail[0] ?? null;
        $remaining = $work !== null ? count(array_filter($work['items'], static fn (array $i) => !$i['done'])) : 0;

        return $this->tabbedPage($model, $summary, [
            ['key' => 'fare', 'label' => 'Da fare', 'icon' => 'list-check', 'color' => 'teal', 'count' => $model->isOpen() && $remaining > 0 ? $remaining : null, 'components' => $detail],
        ], $message, $messageType, $fieldErrors);
    }

    /**
     * Cosa serve per fare la fase dalla sua pagina, secondo il tipo:
     * - data chiave: il form della data (ed email al cliente), si chiude da sola;
     * - approvazione: "Approva" e "Rimanda indietro";
     * - attesa del cliente: "Registra una modifica chiesta" e "Ho sollecitato";
     * - appuntamento: gli appuntamenti del progetto e "Fissa l'appuntamento";
     * - consegna: le cose da consegnare, da spuntare;
     * - facoltativa: anche "Non la facciamo".
     *
     * @return array{form?: array, actions?: array, deliverables?: array, appointments?: array, autoClose?: string}
     */
    private function stepTools(Task $model, \App\Model\WorkOrderTypeStep $step, array $installed): array
    {
        $order = $this->container->get(WorkOrderRepository::class)->find((int) $model->entityId);
        if ($order === null) {
            return [];
        }
        $tools = ['actions' => []];
        $id = (int) $model->id;
        switch ($step->kind) {
            case \App\Work\ProcessService::KIND_MILESTONE:
                $milestone = null;
                foreach ($this->container->get(\App\Repository\WorkOrderMilestoneRepository::class)->findByWorkOrder((int) $order->id) as $m) {
                    if ($step->milestoneId !== null && (int) $m->milestoneId === (int) $step->milestoneId) {
                        $milestone = $m;
                    }
                }
                if ($milestone !== null) {
                    $form = $this->container->get(EditWorkOrderMilestoneTool::class)->formWithOnly(
                        $milestone,
                        ['scheduled_at', 'send_client_email', 'client_email_to', 'client_email_subject', 'client_email_body'],
                        (string) $milestone->label
                    );
                    $form['submitLabel'] = $milestone->scheduledAt !== null ? 'Salva la data' : 'Fissa la data';
                    $form['reloadAfterSave'] = true;
                    if ($milestone->scheduledAt !== null) {
                        // Data gia' fissata: il form e' per cambiarla, parte chiuso.
                        $form['title'] = 'Cambia la data';
                        $form['collapsed'] = true;
                        $sent = $milestone->clientEmailSentAt !== null;
                        $tools['milestone'] = [
                            'label' => (string) $milestone->label,
                            'when' => F::longDate((string) $milestone->scheduledAt) . ' alle ' . substr((string) $milestone->scheduledAt, 11, 5),
                            'appointmentHref' => $milestone->appointmentId !== null ? '/appuntamenti/' . $milestone->appointmentId : null,
                            'email' => $sent ? [
                                'to' => (string) $milestone->clientEmailTo,
                                'sentAt' => date('d/m/Y \a\l\l\e H:i', strtotime((string) $milestone->clientEmailSentAt)),
                                'subject' => (string) $milestone->clientEmailSubject,
                                'body' => (string) $milestone->clientEmailBody,
                            ] : null,
                            'emailOff' => !$sent && (int) $milestone->sendClientEmail !== 1,
                        ];
                    }
                    $tools['form'] = $form;
                    $tools['autoClose'] = 'Si chiude da sola quando fissi la data.';
                }
                break;
            case \App\Work\ProcessService::KIND_APPROVAL:
                if ($step->completesOn === null) {
                    $tools['actions'][] = ['label' => 'Approva', 'action' => "/attivita/{$id}/approva", 'variant' => 'primary', 'icon' => 'thumbs-up'];
                    $tools['actions'][] = ['label' => 'Rimanda indietro', 'action' => "/attivita/{$id}/rimanda", 'variant' => 'secondary', 'ask' => 'Cosa va sistemato?', 'placeholder' => 'Es. il logo e\' troppo simile alla concorrenza'];
                    $tools['autoClose'] = "\"Approva\" la chiude; \"Rimanda indietro\" riapre la fase da rifare con cosa sistemare.";
                }
                break;
            case \App\Work\ProcessService::KIND_CLIENT:
                if ($step->completesOn === null) {
                    $tools['actions'][] = ['label' => 'Registra una modifica chiesta', 'href' => '/commesse/modifiche/nuovo?work_order_id=' . $order->id, 'variant' => 'secondary', 'icon' => 'pen'];
                    $tools['actions'][] = ['label' => 'Ho sollecitato', 'action' => '/commesse/' . $order->id . '/sollecitato', 'variant' => 'ghost', 'icon' => 'bell'];
                }
                break;
            case \App\Work\ProcessService::KIND_MEETING:
                if (in_array('appointments', $installed, true)) {
                    $calendar = $this->container->get(\App\Calendar\CalendarOverviewService::class);
                    $tools['appointments'] = [
                        'items' => $calendar->shortList($this->container->get(\App\Repository\AppointmentRepository::class)->findForEntity('work_orders', (int) $order->id)),
                        'addHref' => '/appuntamenti/nuovo?entity_type=work_orders&entity_id=' . $order->id . '&customer_id=' . $order->customerId . '&title=' . rawurlencode('Appuntamento: ' . $order->title),
                    ];
                }
                break;
            case \App\Work\ProcessService::KIND_DELIVERY:
                $tools['deliverables'] = $this->container->get(\App\Work\ProcessOverviewService::class)->deliverablesFor((int) $order->id);
                break;
        }
        if ((int) $step->isOptional === 1) {
            $tools['actions'][] = ['label' => 'Non la facciamo', 'action' => "/attivita/{$id}/salta", 'variant' => 'ghost', 'confirm' => "Saltare \u{ab}{$model->title}\u{bb}? La fase resta nel progetto come saltata."];
        }

        return $tools;
    }

    /** Cosa si segna nell'esito, secondo il tipo di fase: titolo e un esempio. */
    private const OUTCOME_LABELS = [
        'approvazione' => ["Cosa e' emerso", "Es. piace la bozza 2; il logo va semplificato, colori piu' caldi."],
        'attesa del cliente' => ['Cosa ha risposto il cliente', "Es. gli piace, chiede di provare un verde piu' scuro."],
        'appuntamento' => ["Note sull'appuntamento", "Es. dove, chi c'era, cosa portare."],
        'consegna' => ['Note sulla consegna', 'Es. mandati via WeTransfer il 12/3, confermata la ricezione.'],
        'lavoro' => ['Note ed esito', "Cosa si e' fatto o deciso, link utili, cose da ricordare."],
    ];

    /**
     * Lo spazio di lavoro di una fase di progetto, oltre alle voci:
     * l'esito/note (salvati mentre si scrive), i file (sul Drive, o in
     * attesa) e, per l'approvazione del cliente, data ed email.
     */
    private function workArea(Task $model, ?\App\Model\WorkOrderTypeStep $step): array
    {
        $kind = $step?->kind ?? 'lavoro';
        $area = [];
        if ($kind === \App\Work\ProcessService::KIND_CLIENT_APPROVAL) {
            $area['clientApproval'] = [
                'date' => $model->outcomeDate ?? date('Y-m-d'),
                'how' => (string) $model->outcome,
                'action' => '/attivita/' . $model->id . '/approvato-dal-cliente',
                'done' => !$model->isOpen(),
                'when' => $model->outcomeDate !== null ? F::longDate((string) $model->outcomeDate) : null,
            ];
        } elseif (isset(self::OUTCOME_LABELS[$kind])) {
            [$label, $placeholder] = self::OUTCOME_LABELS[$kind];
            $area['outcome'] = ['label' => $label, 'placeholder' => $placeholder, 'value' => (string) $model->outcome, 'action' => '/attivita/' . $model->id . '/esito'];
        }
        if (\App\Documents\DocumentService::isInstalled() && !in_array($kind, [\App\Work\ProcessService::KIND_TEAM], true)) {
            $documents = $this->container->get(\App\Documents\DocumentService::class);
            $area['files'] = [
                'items' => $documents->filesForTask((int) $model->id),
                'uploadAction' => '/attivita/' . $model->id . '/file',
                'driveReady' => $documents->isReady(),
                'folder' => $step?->folderName,
            ];
        }

        return $area;
    }

    /** Quando si chiude da sola una fase legata al preventivo (vedi ProcessService::onQuoteEvent). */
    private const AUTO_CLOSE = [
        'preventivo preparato' => 'Si chiude da sola quando il preventivo va in approvazione.',
        'preventivo approvato' => "Si chiude da sola quando il preventivo e' approvato.",
        'preventivo inviato' => "Si chiude da sola quando il preventivo e' segnato come inviato.",
        'preventivo accettato' => "Si chiude da sola quando il preventivo e' segnato come accettato.",
    ];

    public function name(): string
    {
        return 'edit_task';
    }

    public function description(): string
    {
        return "Apre un'attivita' (anche una fase di commessa), cercata per id o per titolo/descrizione.";
    }

    public function requiredPermission(): ?string
    {
        return 'tasks.edit';
    }
}
