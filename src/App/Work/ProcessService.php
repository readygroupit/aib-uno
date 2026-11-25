<?php

declare(strict_types=1);

namespace App\Work;

use App\Core\Container;
use App\Model\Customer;
use App\Model\Task;
use App\Model\WorkOrder;
use App\Model\WorkOrderMember;
use App\Model\WorkOrderMilestone;
use App\Model\WorkOrderTypeMilestone;
use App\Model\WorkOrderTypeRole;
use App\Model\WorkOrderTypeStep;
use App\Notifications\NotificationService;
use App\Notifications\NotificationTypes as N;
use App\Package\PackageCatalogService;
use App\Repository\CustomerContactRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerTeamMemberRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderDeliverableRepository;
use App\Repository\WorkOrderMemberRepository;
use App\Repository\WorkOrderMilestoneRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeDeliverableRepository;
use App\Repository\WorkOrderTypeMilestoneRepository;
use App\Repository\WorkOrderTypeRoleRepository;
use App\Repository\WorkOrderTypeStepRepository;
use App\Service\AuthService;
use App\Service\MailService;
use App\Support\ItalianFormat as F;

/**
 * Il processo di un servizio applicato a un progetto (una commessa):
 * - all'avvio la squadra proposta per ruolo (dalla squadra del cliente o
 *   da chi "di solito" lo fa), le date chiave da fissare, cosa si consegna;
 * - ogni fase va a chi ha il suo ruolo, e quando la squadra cambia le
 *   fasi ancora senza nessuno passano a chi e' appena entrato;
 * - le approvazioni approvano o rimandano indietro (un giro in piu');
 * - fissare una data chiave crea l'appuntamento, manda l'email al
 *   cliente (se il processo la prevede, rivista da chi la fissa) e chiude
 *   la sua fase;
 * - il preventivo collegato fa avanzare le fasi commerciali da solo.
 *
 * Avvisa le persone (NotificationService) solo di cio' che le riguarda.
 */
final class ProcessService
{
    public const KIND_WORK = 'lavoro';
    public const KIND_APPROVAL = 'approvazione';
    public const KIND_CLIENT = 'attesa del cliente';
    public const KIND_MILESTONE = 'data chiave';
    public const KIND_BRIEFING = 'briefing';
    /** Raccolta di informazioni (es. per il preventivo): le risposte restano nel progetto. */
    public const KIND_INFO = 'raccolta informazioni';
    /** Comporre la squadra: nella fase si scelgono le persone per ogni ruolo. */
    public const KIND_TEAM = 'squadra';
    /** Fissare un appuntamento: nella fase gli appuntamenti del progetto e "Fissa l'appuntamento". */
    public const KIND_MEETING = 'appuntamento';
    /** La consegna: nella fase le cose da consegnare, da spuntare. */
    public const KIND_DELIVERY = 'consegna';
    /** Approvazione finale del cliente: nella fase si segnano la data e l'email con cui approva. */
    public const KIND_CLIENT_APPROVAL = 'approvazione del cliente';

    /**
     * Fasi che non si chiudono con una spunta: si fanno nella loro pagina
     * (una data da fissare, risposte da scrivere, la squadra, un'approvazione,
     * il preventivo...). Dagli elenchi la spunta apre la fase.
     */
    public static function needsPage(?string $kind, ?string $completesOn = null): bool
    {
        return $completesOn !== null || in_array($kind, [
            self::KIND_MILESTONE, self::KIND_BRIEFING, self::KIND_INFO, self::KIND_TEAM,
            self::KIND_APPROVAL, self::KIND_CLIENT_APPROVAL, self::KIND_MEETING, self::KIND_DELIVERY,
        ], true);
    }

    /** Fasi con dati obbligatori: non si segnano fatte senza (la data, l'approvazione del cliente). */
    public static function needsData(?string $kind): bool
    {
        return in_array($kind, [self::KIND_MILESTONE, self::KIND_CLIENT_APPROVAL], true);
    }

    /**
     * Il pulsante delle fasi "guidate" (domande con le risposte da
     * scrivere): briefing e raccolta informazioni. null per le altre.
     */
    public static function guidedAction(?string $kind, int $taskId): ?array
    {
        $label = match ($kind) {
            self::KIND_BRIEFING => 'Fai il briefing',
            self::KIND_INFO => 'Raccogli le informazioni',
            self::KIND_TEAM => 'Componi la squadra',
            default => null,
        };

        // Le domande stanno nella pagina dell'attivita'.
        return $label === null ? null : ['label' => $label, 'href' => "/attivita/{$taskId}", 'variant' => 'primary', 'icon' => 'comment'];
    }

    public const QUOTE_DRAFTED = 'preventivo preparato';
    public const QUOTE_APPROVED = 'preventivo approvato';
    public const QUOTE_SENT = 'preventivo inviato';
    public const QUOTE_ACCEPTED = 'preventivo accettato';

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /** Il tipo di commessa ha ruoli: e' un processo, non solo un elenco di fasi. */
    public function hasProcess(?int $typeId): bool
    {
        return $typeId !== null && $this->typeRoles()->findByWorkOrderType($typeId) !== [];
    }

    /** Il processo comincia prima del preventivo: ha fasi che si chiudono con il preventivo. */
    public function startsCommercial(?int $typeId): bool
    {
        if ($typeId === null) {
            return false;
        }
        foreach ($this->typeSteps()->findByType($typeId) as $step) {
            if ($step->completesOn !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * All'avvio di un progetto: squadra proposta, date chiave e cose da
     * consegnare copiate dal processo. Le fasi le crea WorkOrderService,
     * che chiede qui a chi va ciascuna (assigneeFor()).
     */
    public function seed(WorkOrder $order): void
    {
        $typeId = (int) $order->workOrderTypeId;
        $members = $this->members();
        $teamByRole = [];
        if ($order->customerId !== null && in_array('customers', PackageCatalogService::installedNames(), true)) {
            foreach ($this->container->get(CustomerTeamMemberRepository::class)->findByCustomer((int) $order->customerId) as $member) {
                $teamByRole[(string) $member->role] ??= (int) $member->userId;
            }
        }
        $already = array_map(static fn (WorkOrderMember $m) => (int) $m->roleId . ':' . (int) $m->userId, $members->findByWorkOrder((int) $order->id));

        foreach ($this->typeRoles()->findByWorkOrderType($typeId) as $role) {
            $userId = ($role->customerTeamRole !== null ? ($teamByRole[$role->customerTeamRole] ?? null) : null) ?? $role->defaultUserId;
            if ($userId === null || in_array((int) $role->id . ':' . (int) $userId, $already, true)) {
                continue;
            }
            $members->insert(['work_order_id' => $order->id, 'user_id' => $userId, 'role_id' => $role->id]);
        }

        if ($this->instanceMilestones()->findByWorkOrder((int) $order->id) === []) {
            foreach ($this->typeMilestones()->findByWorkOrderType($typeId) as $milestone) {
                $hasEmail = trim((string) $milestone->clientEmailSubject) !== '';
                $this->instanceMilestones()->insert([
                    'work_order_id' => $order->id,
                    'milestone_id' => $milestone->id,
                    'position' => $milestone->position ?? 0,
                    'label' => $milestone->label,
                    'send_client_email' => $hasEmail ? 1 : 0,
                    'client_email_to' => $hasEmail ? $this->customerEmails($order) : null,
                    'client_email_subject' => $hasEmail ? $milestone->clientEmailSubject : null,
                    'client_email_body' => $hasEmail ? $milestone->clientEmailBody : null,
                ]);
            }
        }

        if ($this->deliverables()->findByWorkOrder((int) $order->id) === []) {
            foreach ($this->container->get(WorkOrderTypeDeliverableRepository::class)->findByWorkOrderType($typeId) as $deliverable) {
                if ((int) $deliverable->alwaysIncluded !== 1) {
                    continue;
                }
                $this->deliverables()->insert([
                    'work_order_id' => $order->id,
                    'position' => $deliverable->position ?? 0,
                    'label' => $deliverable->label,
                    'description' => $deliverable->description,
                    'is_done' => 0,
                ]);
            }
        }

        foreach ($members->findByWorkOrder((int) $order->id) as $member) {
            $this->notifyMember($order, $member);
        }
    }

    /** A chi va una fase del modello: il primo della squadra con quel ruolo; senza ruolo, il responsabile. */
    public function assigneeFor(WorkOrder $order, WorkOrderTypeStep $step): ?int
    {
        if ($step->roleId === null) {
            return $order->ownerUserId;
        }
        foreach ($this->members()->findByWorkOrder((int) $order->id) as $member) {
            if ((int) $member->roleId === (int) $step->roleId) {
                return (int) $member->userId;
            }
        }

        return null;
    }

    /**
     * La squadra e' cambiata: le fasi ancora aperte e senza nessuno (o di
     * chi non e' piu' nel ruolo) passano a chi ha il ruolo adesso.
     *
     * @return int fasi riassegnate
     */
    public function reassign(int $workOrderId): int
    {
        $order = $this->orders()->find($workOrderId);
        if (!$order instanceof WorkOrder) {
            return 0;
        }
        $byRole = [];
        foreach ($this->members()->findByWorkOrder($workOrderId) as $member) {
            $byRole[(int) $member->roleId][] = (int) $member->userId;
        }
        $steps = $this->stepsById((int) $order->workOrderTypeId);
        $moved = [];
        foreach ($this->tasks()->findForEntity(WorkOrderService::ENTITY, $workOrderId) as $task) {
            $step = $steps[(int) $task->sourceStepId] ?? null;
            if (!$task->isOpen() || $step === null || $step->roleId === null) {
                continue;
            }
            $holders = $byRole[(int) $step->roleId] ?? [];
            if ($holders === [] || in_array((int) $task->assignedUserId, $holders, true)) {
                continue;
            }
            $this->tasks()->update((int) $task->id, ['assigned_user_id' => $holders[0]]);
            $moved[$holders[0]][] = $task;
        }
        // Un avviso per persona, non uno per fase.
        foreach ($moved as $userId => $tasks) {
            $title = count($tasks) === 1
                ? "Nuova attivita' per te: {$tasks[0]->title}"
                : 'Ti sono passate ' . count($tasks) . " fasi di \u{ab}{$order->title}\u{bb}";
            $body = implode("\n", array_map(static fn (Task $t) => '- ' . $t->title, $tasks));
            $this->notify((int) $userId, N::TASK_ASSIGNED, $title, $order, count($tasks) === 1 ? '/attivita/' . $tasks[0]->id : null, $body);
        }

        return array_sum(array_map('count', $moved));
    }

    /**
     * Una persona in un ruolo del progetto (dalla fase "Comporre la
     * squadra"): entra nella squadra, riceve l'avviso e le fasi aperte del
     * ruolo passano a lei. Un ruolo puo' avere piu' persone (es. gli aiuti).
     *
     * @return string cosa e' successo
     */
    public function assignRole(int $workOrderId, int $roleId, int $userId): string
    {
        $order = $this->orders()->find($workOrderId);
        $role = $this->typeRoles()->find($roleId);
        if (!$order instanceof WorkOrder || $role === null || (int) $role->workOrderTypeId !== (int) $order->workOrderTypeId) {
            return 'Ruolo non trovato.';
        }
        foreach ($this->members()->findByWorkOrder($workOrderId) as $member) {
            if ((int) $member->roleId === $roleId && (int) $member->userId === $userId) {
                return "Gia' nella squadra.";
            }
        }
        $id = $this->members()->insert(['work_order_id' => $workOrderId, 'user_id' => $userId, 'role_id' => $roleId]);
        $member = $this->members()->find($id);
        if ($member instanceof WorkOrderMember) {
            $this->notifyMember($order, $member);
        }
        $moved = $this->reassign($workOrderId);

        return "Aggiunto come \u{ab}{$role->label}\u{bb}." . ($moved > 0 ? " {$moved} fasi passate a lui." : '');
    }

    /** Toglie una persona dalla squadra; le sue fasi aperte passano a chi resta nel ruolo. */
    public function removeMember(int $memberId): string
    {
        $member = $this->members()->find($memberId);
        if (!$member instanceof WorkOrderMember) {
            return 'Persona non trovata.';
        }
        $this->members()->delete($memberId);
        $moved = $this->reassign((int) $member->workOrderId);

        return 'Tolto dalla squadra.' . ($moved > 0 ? " {$moved} fasi passate a chi resta nel ruolo." : '');
    }

    public function notifyMember(WorkOrder $order, WorkOrderMember $member): void
    {
        $role = $member->roleId !== null ? $this->typeRoles()->find((int) $member->roleId) : null;
        $this->notify(
            (int) $member->userId,
            N::PROJECT_MEMBER_ADDED,
            "Sei nel progetto \u{ab}{$order->title}\u{bb}" . ($role instanceof WorkOrderTypeRole ? ' come ' . mb_strtolower((string) $role->label) : ''),
            $order
        );
    }

    /**
     * Una fase chiusa: chi ha quella dopo viene avvisato che tocca a lui
     * (o che c'e' da approvare).
     */
    public function afterTaskDone(Task $done): void
    {
        if ($done->entityType !== WorkOrderService::ENTITY || $done->entityId === null) {
            return;
        }
        $order = $this->orders()->find((int) $done->entityId);
        if (!$order instanceof WorkOrder) {
            return;
        }
        // La fase subito dopo quella chiusa (non la prima rimasta aperta:
        // una fase indietro dimenticata non deve far avvisare la persona sbagliata).
        $next = null;
        foreach ($this->tasks()->findForEntity(WorkOrderService::ENTITY, (int) $order->id) as $task) {
            $after = (int) $task->position > (int) $done->position
                || ((int) $task->position === (int) $done->position && (int) $task->id > (int) $done->id);
            if ($after && $task->isOpen()) {
                $next = $task;
                break;
            }
        }
        if ($next === null || $next->assignedUserId === null) {
            return;
        }
        $step = $this->stepsById((int) $order->workOrderTypeId)[(int) $next->sourceStepId] ?? null;
        $isApproval = $step instanceof WorkOrderTypeStep && $step->kind === self::KIND_APPROVAL;
        $this->notify(
            (int) $next->assignedUserId,
            $isApproval ? N::APPROVAL_REQUESTED : N::STEP_READY,
            $isApproval ? "Da approvare: {$next->title}" : "Tocca a te: {$next->title}",
            $order,
            '/attivita/' . $next->id
        );
    }

    /** @return ?WorkOrderTypeStep la fase del modello da cui nasce l'attivita' */
    public function stepFor(Task $task): ?WorkOrderTypeStep
    {
        if ($task->sourceStepId === null) {
            return null;
        }
        $step = $this->typeSteps()->find((int) $task->sourceStepId);

        return $step instanceof WorkOrderTypeStep ? $step : null;
    }

    /**
     * Approvazione negata: l'approvazione si chiude come "rimandata" e
     * nascono un nuovo giro della fase da rifare e una nuova approvazione,
     * subito dopo.
     */
    public function reject(int $taskId, string $note): ?Task
    {
        $task = $this->tasks()->find($taskId);
        $step = $task instanceof Task ? $this->stepFor($task) : null;
        if (!$task instanceof Task || $step === null || $step->kind !== self::KIND_APPROVAL || !$task->isOpen()) {
            return null;
        }
        $order = $this->orders()->find((int) $task->entityId);
        if (!$order instanceof WorkOrder) {
            return null;
        }

        $note = trim($note);
        $who = (string) $this->container->get(AuthService::class)->currentUserName();
        $this->tasks()->update($taskId, [
            'stage' => 'fatta',
            'completed_at' => date('Y-m-d H:i:s'),
            'description' => trim((string) $task->description . "\n\nRimandata indietro" . ($who !== '' ? " da {$who}" : '') . ' il ' . date('d/m/Y') . ($note !== '' ? ": {$note}" : '.')),
        ]);

        $iteration = (int) ($task->iteration ?? 1) + 1;
        $redo = $step->rejectStepId !== null ? $this->typeSteps()->find((int) $step->rejectStepId) : null;
        $created = null;
        if ($redo instanceof WorkOrderTypeStep) {
            $id = $this->tasks()->insert($this->taskData($order, $redo, (int) $task->position, $iteration, $note !== '' ? "Da sistemare: {$note}" : null));
            $created = $this->tasks()->find($id);
            if ($created instanceof Task && $created->assignedUserId !== null) {
                $this->notify((int) $created->assignedUserId, N::STEP_READY, "Da rivedere: {$created->title}", $order, '/attivita/' . $id, $note !== '' ? $note : null);
            }
        }
        $this->tasks()->insert($this->taskData($order, $step, (int) $task->position, $iteration));
        $this->container->get(WorkOrderService::class)->refresh((int) $order->id);

        return $created instanceof Task ? $created : null;
    }

    /** Una fase facoltativa che la squadra decide di non fare. */
    public function skip(int $taskId): bool
    {
        $task = $this->tasks()->find($taskId);
        $step = $task instanceof Task ? $this->stepFor($task) : null;
        if (!$task instanceof Task || $step === null || (int) $step->isOptional !== 1 || !$task->isOpen()) {
            return false;
        }
        $this->tasks()->update($taskId, ['stage' => 'annullata', 'completed_at' => date('Y-m-d H:i:s')]);
        $this->container->get(WorkOrderService::class)->refresh((int) $task->entityId);
        $this->afterTaskDone($task);

        return true;
    }

    /** @return array<string,mixed> i dati di un'attivita' nata da una fase del modello */
    public function taskData(WorkOrder $order, WorkOrderTypeStep $step, int $position, int $iteration = 1, ?string $extraDescription = null, ?string $plannedDate = null): array
    {
        $title = (string) $step->title . ($iteration > 1 ? " ({$iteration}\u{b0} giro)" : '');

        return [
            'title' => $title,
            'description' => trim((string) $step->description . ($extraDescription !== null ? "\n\n{$extraDescription}" : '')) ?: null,
            'checklist' => $step->checklist,
            'position' => $position,
            'planned_date' => $plannedDate,
            'estimated_minutes' => $step->estimatedMinutes,
            'waits_for_client' => ((int) ($step->waitsForClient ?? 0) === 1 || $step->kind === self::KIND_CLIENT) ? 1 : 0,
            'assigned_user_id' => $this->assigneeFor($order, $step),
            'priority' => $order->priority ?? 'normale',
            'stage' => 'da fare',
            'entity_type' => WorkOrderService::ENTITY,
            'entity_id' => $order->id,
            'source_step_id' => $step->id,
            'iteration' => $iteration,
        ];
    }

    /**
     * Un evento del preventivo collegato a un progetto: chiude le fasi
     * che aspettavano proprio quello e sposta lo stato commerciale.
     */
    public function onQuoteEvent(int $quoteId, string $event, ?string $detail = null): void
    {
        if (!in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            return;
        }
        $order = $this->orders()->findByQuote($quoteId);
        if (!$order instanceof WorkOrder) {
            return;
        }
        $this->completeStepsOn($order, $event);

        $data = [];
        if ($event === 'preventivo rifiutato') {
            $data = ['stage' => 'non acquisita', 'lost_reason' => $detail !== null && $detail !== '' ? mb_substr($detail, 0, 255) : 'Preventivo rifiutato'];
            if (\App\Knowledge\KnowledgeService::isInstalled()) {
                $this->container->get(\App\Knowledge\KnowledgeService::class)->syncRoadmap((int) $order->id, 'scartata');
            }
        } elseif ($event === self::QUOTE_ACCEPTED) {
            $data = ['stage' => 'da avviare', 'start_date' => date('Y-m-d')];
            $sold = $this->container->get(\App\Repository\QuoteLineRepository::class)->findByQuote($quoteId);
            foreach ($sold as $line) {
                if ($line->customerServiceId !== null) {
                    $data['customer_service_id'] ??= $line->customerServiceId;
                }
            }
        } elseif ($order->stage === 'contatto') {
            $data = ['stage' => 'preventivo'];
        }
        if ($data !== []) {
            $this->orders()->update((int) $order->id, $data + ['last_activity_at' => date('Y-m-d H:i:s')]);
        }
        $this->container->get(WorkOrderService::class)->refresh((int) $order->id);
    }

    /** Il preventivo del progetto e' stato appena creato: il progetto passa a "preventivo". */
    public function linkQuote(int $workOrderId, int $quoteId): void
    {
        $order = $this->orders()->find($workOrderId);
        if (!$order instanceof WorkOrder) {
            return;
        }
        $this->orders()->update($workOrderId, ['quote_id' => $quoteId] + ($order->stage === 'contatto' ? ['stage' => 'preventivo'] : []));
        if ($order->customerId !== null) {
            $this->container->get(\App\Customers\CustomerFollowUpService::class)->negotiating((int) $order->customerId);
        }
    }

    /**
     * Una data chiave salvata. Solo la prima volta che ha una data (o
     * quando la data cambia): appuntamento, email al cliente una volta
     * sola, fase chiusa, squadra avvisata.
     *
     * @return string cosa e' successo, per il messaggio dopo il salvataggio
     */
    public function onMilestoneSaved(int $milestoneId, ?string $previousDate): string
    {
        $milestone = $this->instanceMilestones()->find($milestoneId);
        if (!$milestone instanceof WorkOrderMilestone || $milestone->scheduledAt === null) {
            return 'Salvata.';
        }
        $order = $this->orders()->find((int) $milestone->workOrderId);
        if (!$order instanceof WorkOrder) {
            return 'Salvata.';
        }
        $model = $milestone->milestoneId !== null ? $this->typeMilestones()->find((int) $milestone->milestoneId) : null;
        $changed = $previousDate === null || substr($previousDate, 0, 16) !== substr((string) $milestone->scheduledAt, 0, 16);
        $done = [];

        if ($changed && $model instanceof WorkOrderTypeMilestone && (int) $model->createsAppointment === 1 && in_array('appointments', PackageCatalogService::installedNames(), true)) {
            $done[] = $this->saveAppointment($order, $milestone);
        }

        if ((int) $milestone->sendClientEmail === 1 && $milestone->clientEmailSentAt === null) {
            $done[] = $this->sendClientEmail($order, $milestone);
        }

        if ($changed) {
            $when = F::longDate((string) $milestone->scheduledAt) . ' alle ' . substr((string) $milestone->scheduledAt, 11, 5);
            foreach ($this->members()->findByWorkOrder((int) $order->id) as $member) {
                $this->notify((int) $member->userId, N::MILESTONE_SET, "{$milestone->label}: {$when}", $order);
            }
            if ($milestone->milestoneId !== null) {
                $this->completeMilestoneSteps($order, (int) $milestone->milestoneId);
            }
        }

        $done = array_values(array_filter($done));

        return 'Data salvata' . ($done !== [] ? ': ' . implode(', ', $done) . '.' : '.');
    }

    /**
     * L'email della data chiave con i segnaposto riempiti: si mostra cosi'
     * nel form, chi la fissa la rilegge e la cambia se serve.
     *
     * @return array{subject:string, body:string}
     */
    public function renderEmail(WorkOrder $order, string $subject, string $body, ?string $scheduledAt): array
    {
        $customer = $order->customerId !== null ? $this->container->get(CustomerRepository::class)->find((int) $order->customerId) : null;
        $team = [];
        $users = $this->container->get(UserRepository::class);
        foreach ($this->members()->findByWorkOrder((int) $order->id) as $member) {
            $user = $users->find((int) $member->userId);
            $role = $member->roleId !== null ? $this->typeRoles()->find((int) $member->roleId) : null;
            if ($user !== null) {
                $team[] = trim("{$user->firstName} {$user->lastName}") . ($role instanceof WorkOrderTypeRole ? ' (' . mb_strtolower((string) $role->label) . ')' : '');
            }
        }
        $me = $this->container->get(AuthService::class)->currentUserName();
        $values = [
            '{cliente}' => $customer instanceof Customer ? $customer->displayName() : '',
            '{progetto}' => (string) $order->title,
            '{squadra}' => $team !== [] ? implode(', ', array_unique($team)) : '',
            '{firma}' => trim(($me !== null && $me !== '' ? $me . "\n" : '') . (defined('APP_NAME') ? APP_NAME : '')),
        ];
        if ($scheduledAt !== null && $scheduledAt !== '') {
            $values['{data}'] = F::longDate($scheduledAt) . ' alle ' . substr($scheduledAt, 11, 5);
        }

        return ['subject' => strtr($subject, $values), 'body' => strtr($body, $values)];
    }

    private function sendClientEmail(WorkOrder $order, WorkOrderMilestone $milestone): ?string
    {
        $to = array_values(array_filter(array_map('trim', explode(',', (string) $milestone->clientEmailTo))));
        if ($to === [] || trim((string) $milestone->clientEmailSubject) === '') {
            return 'email non inviata (manca l\'indirizzo o l\'oggetto)';
        }
        $email = $this->renderEmail($order, (string) $milestone->clientEmailSubject, (string) $milestone->clientEmailBody, $milestone->scheduledAt);
        $mail = $this->container->get(MailService::class);
        $sent = 0;
        foreach ($to as $address) {
            $sent += $mail->send($address, $email['subject'], $email['body']) ? 1 : 0;
        }
        $this->instanceMilestones()->update((int) $milestone->id, [
            'client_email_sent_at' => date('Y-m-d H:i:s'),
            'client_email_subject' => $email['subject'],
            'client_email_body' => $email['body'],
        ]);

        return $sent > 0 ? 'email mandata a ' . implode(', ', $to) : 'email non partita (controlla l\'invio delle email)';
    }

    private function saveAppointment(WorkOrder $order, WorkOrderMilestone $milestone): ?string
    {
        $appointments = $this->container->get(\App\Repository\AppointmentRepository::class);
        $attendees = array_values(array_unique(array_map(static fn (WorkOrderMember $m) => (int) $m->userId, $this->members()->findByWorkOrder((int) $order->id))));
        $data = [
            'title' => "{$milestone->label} \u{2013} {$order->title}",
            'start_at' => $milestone->scheduledAt,
            'end_at' => date('Y-m-d H:i:s', strtotime((string) $milestone->scheduledAt) + 3600),
            'customer_id' => $order->customerId,
            'entity_type' => WorkOrderService::ENTITY,
            'entity_id' => $order->id,
            'attendee_user_ids' => $attendees !== [] ? implode(',', $attendees) : null,
        ];
        if ($milestone->appointmentId !== null && $appointments->find((int) $milestone->appointmentId) !== null) {
            $appointments->update((int) $milestone->appointmentId, $data);
            $id = (int) $milestone->appointmentId;
            $text = 'appuntamento spostato';
        } else {
            $id = $appointments->insert($data + [
                'kind' => 'incontro in sede',
                'stage' => 'da confermare',
                'assigned_user_id' => $this->container->get(AuthService::class)->currentUserId() ?? $order->ownerUserId,
            ]);
            $this->instanceMilestones()->update((int) $milestone->id, ['appointment_id' => $id]);
            $text = 'appuntamento in calendario';
        }
        $error = $this->container->get(\App\Calendar\CalendarSyncService::class)->push($id);

        return $error === null ? $text : $text . " ({$error})";
    }

    private function completeStepsOn(WorkOrder $order, string $event): void
    {
        $steps = $this->stepsById((int) $order->workOrderTypeId);
        $taskService = $this->container->get(TaskService::class);
        foreach ($this->tasks()->findForEntity(WorkOrderService::ENTITY, (int) $order->id) as $task) {
            $step = $steps[(int) $task->sourceStepId] ?? null;
            if ($task->isOpen() && $step !== null && $step->completesOn === $event) {
                $taskService->complete((int) $task->id);
            }
        }
    }

    private function completeMilestoneSteps(WorkOrder $order, int $typeMilestoneId): void
    {
        $steps = $this->stepsById((int) $order->workOrderTypeId);
        $taskService = $this->container->get(TaskService::class);
        foreach ($this->tasks()->findForEntity(WorkOrderService::ENTITY, (int) $order->id) as $task) {
            $step = $steps[(int) $task->sourceStepId] ?? null;
            if ($task->isOpen() && $step !== null && (int) $step->milestoneId === $typeMilestoneId) {
                $taskService->complete((int) $task->id);
            }
        }
    }

    /** Gli indirizzi a cui scrivere: l'email del cliente, altrimenti dei referenti che decidono. */
    public function customerEmails(WorkOrder $order): ?string
    {
        return $order->customerId !== null ? $this->emailsForCustomer((int) $order->customerId) : null;
    }

    public function emailsForCustomer(int $customerId): ?string
    {
        if (!in_array('customers', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $customer = $this->container->get(CustomerRepository::class)->find($customerId);
        if ($customer instanceof Customer && trim((string) $customer->email) !== '') {
            return trim((string) $customer->email);
        }
        $emails = [];
        foreach ($this->container->get(CustomerContactRepository::class)->findByCustomer($customerId) as $contact) {
            if (trim((string) $contact->email) !== '' && ((int) $contact->isDecisionMaker === 1 || $emails === [])) {
                $emails[] = trim((string) $contact->email);
            }
        }

        return $emails !== [] ? implode(', ', array_unique($emails)) : null;
    }

    private function notify(int $userId, string $type, string $title, WorkOrder $order, ?string $url = null, ?string $body = null): void
    {
        $this->container->get(NotificationService::class)->notify(
            $userId,
            $type,
            $title,
            $body ?? "Progetto: {$order->title}",
            $url ?? '/commesse/' . $order->id,
            WorkOrderService::ENTITY,
            (int) $order->id
        );
    }

    /** @return array<int, WorkOrderTypeStep> */
    private function stepsById(int $typeId): array
    {
        $result = [];
        foreach ($this->typeSteps()->findByType($typeId) as $step) {
            $result[(int) $step->id] = $step;
        }

        return $result;
    }

    private function orders(): WorkOrderRepository
    {
        return $this->container->get(WorkOrderRepository::class);
    }

    private function tasks(): TaskRepository
    {
        return $this->container->get(TaskRepository::class);
    }

    private function members(): WorkOrderMemberRepository
    {
        return $this->container->get(WorkOrderMemberRepository::class);
    }

    private function typeRoles(): WorkOrderTypeRoleRepository
    {
        return $this->container->get(WorkOrderTypeRoleRepository::class);
    }

    private function typeSteps(): WorkOrderTypeStepRepository
    {
        return $this->container->get(WorkOrderTypeStepRepository::class);
    }

    private function typeMilestones(): WorkOrderTypeMilestoneRepository
    {
        return $this->container->get(WorkOrderTypeMilestoneRepository::class);
    }

    private function instanceMilestones(): WorkOrderMilestoneRepository
    {
        return $this->container->get(WorkOrderMilestoneRepository::class);
    }

    private function deliverables(): WorkOrderDeliverableRepository
    {
        return $this->container->get(WorkOrderDeliverableRepository::class);
    }
}
