<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\Task;
use App\Prompt\Tool\EditTaskTool;
use App\Prompt\Tool\ListTasksTool;
use App\Repository\TaskRepository;
use App\Support\ItalianFormat as F;
use App\Work\TaskService;

final class TasksController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return TaskRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditTaskTool::class;
    }

    protected function listToolClass(): string
    {
        return ListTasksTool::class;
    }

    protected function packageName(): string
    {
        return 'tasks';
    }

    protected function listPageTitle(): string
    {
        return "Attivita'";
    }

    protected function newPageTitle(): string
    {
        return 'Nuova attivita';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica attivita';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'da fare', 'priority' => 'normale', 'planned_date' => date('Y-m-d'), 'waits_for_client' => '0'];
    }

    protected function createdMessage(): string
    {
        return "Attivita' creata.";
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return in_array($action, ['toggle', 'check', 'approve', 'reject', 'skip', 'briefing', 'outcome', 'itemNote', 'upload', 'clientApproved', 'assign'], true) ? 'tasks.edit' : parent::requiredPermissionForAction($action);
    }

    /** Le mie, oppure ?tutte=1 per quelle di tutto il team. */
    public function indexAction(): ?string
    {
        $scope = $this->request->get('tutte') ? 'tutte' : 'mie';
        $components = $this->container->get(ListTasksTool::class)->execute(['scope' => $scope]);

        return $this->renderPage($components, ['title' => $this->listPageTitle()]);
    }

    /** Una fase di commessa nuova o cambiata riallinea la commessa (stato, attese). */
    protected function afterCreate(int $id): void
    {
        $task = $this->repository()->find($id);
        if ($task instanceof Task) {
            $this->container->get(TaskService::class)->afterChange($task);
        }
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $this->afterCreate($id);
        $task = $this->repository()->find($id);
        if ($task instanceof Task && $before instanceof Task && $task->assignedUserId !== null && $task->assignedUserId !== $before->assignedUserId) {
            $this->container->get(\App\Notifications\NotificationService::class)->notify(
                (int) $task->assignedUserId,
                \App\Notifications\NotificationTypes::TASK_ASSIGNED,
                "Nuova attivita' per te: {$task->title}",
                null,
                '/attivita/' . $id,
                'tasks',
                $id
            );
        }

        return null;
    }

    /** Fatta / da rifare, con un clic dalle liste. */
    public function toggleAction(): void
    {
        $task = $this->repository()->find((int) $this->param('id'));
        if (!$task instanceof Task) {
            $this->json(['ok' => false, 'message' => "Attivita' non trovata."], 404);

            return;
        }

        $service = $this->container->get(TaskService::class);
        if (!$task->isOpen()) {
            $service->reopen((int) $task->id);
            $this->json(['ok' => true, 'message' => "Riaperta: \u{ab}{$task->title}\u{bb}."]);

            return;
        }

        // Una fase con dati obbligatori (la data, l'approvazione del cliente) non si
        // segna fatta senza: si fa dalla sua pagina.
        $missing = $this->missingData($task);
        if ($missing !== null) {
            $this->json(['ok' => false, 'message' => $missing], 422);

            return;
        }

        $minutes = (int) $this->request->get('minutes', 0);
        $next = $service->complete((int) $task->id, $minutes > 0 ? $minutes : null);
        $message = "Fatta: \u{ab}{$task->title}\u{bb}.";
        if ($next !== null) {
            $message .= " La prossima e' " . F::relativeDays((string) $next->plannedDate, date('Y-m-d')) . '.';
        }
        $this->json(['ok' => true, 'message' => $message]);
    }

    /** Un punto della checklist spuntato (o tolto) dalla pagina dell'attivita'. */
    public function checkAction(): void
    {
        $done = $this->container->get(TaskService::class)->toggleChecklistItem((int) $this->param('id'), (int) $this->request->get('index', -1));
        if ($done === null) {
            $this->json(['ok' => false, 'message' => 'Punto non trovato.'], 404);

            return;
        }
        $this->json(['ok' => true, 'done' => $done]);
    }

    /** Cosa manca prima di poter segnare fatta una fase, null se niente. */
    private function missingData(Task $task): ?string
    {
        if ($task->sourceStepId === null || !in_array('work_orders', \App\Package\PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $step = $this->container->get(\App\Repository\WorkOrderTypeStepRepository::class)->find((int) $task->sourceStepId);
        if ($step === null || !\App\Work\ProcessService::needsData($step->kind)) {
            return null;
        }
        if ($step->kind === \App\Work\ProcessService::KIND_CLIENT_APPROVAL) {
            return $task->outcomeDate === null ? "Prima segna la data e l'email dell'approvazione: si fa nella fase." : null;
        }
        foreach ($this->container->get(\App\Repository\WorkOrderMilestoneRepository::class)->findByWorkOrder((int) $task->entityId) as $milestone) {
            if ($step->milestoneId !== null && (int) $milestone->milestoneId === (int) $step->milestoneId) {
                return $milestone->scheduledAt === null ? 'Prima fissa la data: si fa nella fase.' : null;
            }
        }

        return null;
    }

    /** A chi tocca questa attivita' (dalla sua pagina). */
    public function assignAction(): void
    {
        $userId = (int) $this->request->get('user_id', 0);
        $user = $userId > 0 ? $this->container->get(\App\Repository\UserRepository::class)->find($userId) : null;
        $task = $user !== null ? $this->container->get(TaskService::class)->assign((int) $this->param('id'), $userId) : null;
        if ($task === null) {
            $this->json(['ok' => false, 'message' => 'Scegli una persona.'], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => "Ora tocca a " . trim("{$user->firstName} {$user->lastName}") . ': ha ricevuto l\'avviso.']);
    }

    /** L'esito e le note della fase (si salva mentre si scrive). */
    public function outcomeAction(): void
    {
        $ok = $this->container->get(\App\Work\TaskWorkService::class)->saveOutcome((int) $this->param('id'), (string) $this->request->get('text', ''));
        $this->json(['ok' => $ok, 'message' => $ok ? 'Salvato.' : "Attivita' non trovata."], $ok ? 200 : 404);
    }

    /** La nota di una voce della checklist (si salva mentre si scrive). */
    public function itemNoteAction(): void
    {
        $ok = $this->container->get(\App\Work\TaskWorkService::class)->saveItemNote((int) $this->param('id'), (int) $this->request->get('index', -1), (string) $this->request->get('text', ''));
        $this->json(['ok' => $ok, 'message' => $ok ? 'Salvato.' : 'Voce non trovata.'], $ok ? 200 : 404);
    }

    /** Un file dalla fase: sul Drive nella cartella della fase, o in attesa se il Drive non c'e'. */
    public function uploadAction(): void
    {
        $task = $this->repository()->find((int) $this->param('id'));
        $file = $this->request->file('file');
        if (!$task instanceof Task || $file === null) {
            $this->json(['ok' => false, 'message' => $file === null ? 'Nessun file arrivato (troppo grande per il server?).' : "Attivita' non trovata."], 422);

            return;
        }
        if (!\App\Documents\DocumentService::isInstalled()) {
            $this->json(['ok' => false, 'message' => 'Per caricare file serve il pacchetto Documenti.'], 422);

            return;
        }
        try {
            $document = $this->container->get(\App\Documents\DocumentService::class)->uploadForTask(
                $task,
                (string) $file['tmp_name'],
                (string) $file['name'],
                (string) ($file['type'] ?? ''),
                (string) $this->request->get('description', '')
            );
        } catch (\App\Connector\ConnectorException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 502);

            return;
        }
        $this->json(['ok' => true, 'message' => $document->localPath !== null
            ? "Caricato: \u{ab}{$document->name}\u{bb}. Va sul Drive appena e' collegato."
            : "Caricato sul Drive: \u{ab}{$document->name}\u{bb}."]);
    }

    /** Approvazione finale del cliente: data ed email, poi la fase si chiude. */
    public function clientApprovedAction(): void
    {
        $error = $this->container->get(\App\Work\TaskWorkService::class)->clientApproved(
            (int) $this->param('id'),
            (string) $this->request->get('date', ''),
            (string) $this->request->get('how', '')
        );
        $this->json(['ok' => $error === null, 'message' => $error ?? "Segnata l'approvazione del cliente: fase fatta."], $error === null ? 200 : 422);
    }

    /** Approvazione di una fase del processo: solo chi ce l'ha assegnata (o chi gestisce le commesse). */
    public function approveAction(): void
    {
        $task = $this->processTask();
        if ($task === null) {
            return;
        }
        $this->container->get(TaskService::class)->complete((int) $task->id);
        $this->json(['ok' => true, 'message' => "Approvata: \u{ab}{$task->title}\u{bb}."]);
    }

    /** Rimandata indietro, con cosa sistemare: nasce un altro giro della fase da rifare. */
    public function rejectAction(): void
    {
        $task = $this->processTask();
        if ($task === null) {
            return;
        }
        $redo = $this->container->get(\App\Work\ProcessService::class)->reject((int) $task->id, (string) $this->request->get('reason', ''));
        $this->json(['ok' => true, 'message' => $redo !== null ? "Rimandata: \u{ab}{$redo->title}\u{bb} torna da rifare." : 'Rimandata indietro.']);
    }

    /** Una fase facoltativa che la squadra decide di non fare (es. la moodboard). */
    public function skipAction(): void
    {
        $task = $this->processTask();
        if ($task === null) {
            return;
        }
        $ok = $this->container->get(\App\Work\ProcessService::class)->skip((int) $task->id);
        $this->json(['ok' => $ok, 'message' => $ok ? "Saltata: \u{ab}{$task->title}\u{bb}." : 'Questa fase non si puo\' saltare.'], $ok ? 200 : 422);
    }

    /**
     * Le risposte di una fase guidata (briefing o raccolta informazioni),
     * dalla scheda "Da fare" dell'attivita': mode=autosave mentre si scrive
     * (solo le risposte); complete=1 chiude la fase e ridisegna la pagina,
     * con l'esito in cima. Aperto con GET
     * (vecchi link), porta alla pagina dell'attivita'.
     */
    public function briefingAction(): ?string
    {
        $task = $this->repository()->find((int) $this->param('id'));
        if (!$task instanceof Task || !\App\Knowledge\KnowledgeService::isInstalled()) {
            $this->redirect('/attivita');

            return null;
        }
        if ($this->request->getMethod() !== 'POST') {
            $this->redirect('/attivita/' . $task->id);

            return null;
        }
        $knowledge = $this->container->get(\App\Knowledge\KnowledgeService::class);
        $answers = [];
        foreach (array_keys($knowledge->briefingQuestions($task)) as $i) {
            $answers[$i] = (string) $this->request->get('q' . $i, '');
        }
        // Mentre si scrive: si salvano solo le risposte, la pagina resta com'e'.
        if ((string) $this->request->get('mode', '') === 'autosave') {
            $ok = $knowledge->saveAnswers((int) $task->id, $answers);
            $this->json(['ok' => $ok, 'message' => $ok ? 'Salvato.' : 'Non salvato.'], $ok ? 200 : 422);

            return null;
        }
        $message = $knowledge->saveBriefing((int) $task->id, $answers, (string) $this->request->get('complete', '0') === '1');
        $fresh = $this->repository()->find((int) $task->id);
        $this->json(['ok' => true, 'message' => $message, 'components' => $this->container->get(EditTaskTool::class)->buildPage($fresh, $message, 'success')]);

        return null;
    }

    private function processTask(): ?Task
    {
        $task = $this->repository()->find((int) $this->param('id'));
        if (!$task instanceof Task || !$task->isOpen()) {
            $this->json(['ok' => false, 'message' => "Attivita' non trovata o gia' chiusa."], 404);

            return null;
        }
        $auth = $this->container->get(\App\Service\AuthService::class);
        if ((int) $task->assignedUserId !== (int) $auth->currentUserId() && !$auth->hasPermission('work_orders.edit')) {
            $this->json(['ok' => false, 'message' => "Non e' una tua attivita'."], 403);

            return null;
        }

        return $task;
    }
}
