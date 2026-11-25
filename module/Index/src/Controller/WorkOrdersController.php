<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\WorkOrder;
use App\Prompt\Tool\EditWorkOrderTool;
use App\Prompt\Tool\ListWorkOrdersTool;
use App\Repository\TaskRepository;
use App\Repository\WorkOrderRepository;
use App\Support\ItalianFormat as F;
use App\Work\TaskService;
use App\Work\WorkOrderService;

/**
 * Commesse. Alla creazione le fasi del tipo diventano attivita'; le azioni
 * con un clic (attesa del cliente, sollecitato, ripresa, pausa, consegna)
 * rispondono con la scheda aggiornata.
 */
final class WorkOrdersController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return WorkOrderRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditWorkOrderTool::class;
    }

    protected function listToolClass(): string
    {
        return ListWorkOrdersTool::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function listPageTitle(): string
    {
        return 'Commesse';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova commessa';
    }

    protected function editPageTitle(): string
    {
        return 'Commessa';
    }

    protected function defaultsOnCreate(): array
    {
        // Un processo che parte dal primo contatto nasce in "contatto".
        $typeId = (int) $this->request->get('work_order_type_id', 0);
        $commercial = $typeId > 0 && $this->container->get(\App\Work\ProcessService::class)->startsCommercial($typeId);

        return [
            'stage' => $commercial ? 'contatto' : 'da avviare',
            'priority' => 'normale',
            'start_date' => date('Y-m-d'),
            'owner_user_id' => $this->container->get(\App\Service\AuthService::class)->currentUserId(),
        ];
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        if ($action === 'quote') {
            return 'quotes.create';
        }
        if ($action === 'fromIdea') {
            return 'work_orders.create';
        }

        return in_array($action, ['wait', 'resume', 'pause', 'deliver', 'reminded', 'lost'], true)
            ? 'work_orders.edit'
            : parent::requiredPermissionForAction($action);
    }

    protected function afterCreate(int $id): void
    {
        // Un processo con la parte commerciale (fasi che si chiudono col
        // preventivo) parte dal primo contatto, anche se nel form e' rimasto
        // "da avviare".
        $order = $this->repository()->find($id);
        if ($order instanceof WorkOrder && $order->stage === 'da avviare' && $order->quoteId === null
            && $this->container->get(\App\Work\ProcessService::class)->startsCommercial($order->workOrderTypeId !== null ? (int) $order->workOrderTypeId : null)) {
            $this->repository()->update($id, ['stage' => 'contatto']);
        }
        $this->service()->createStepsFromType($id);
        // Le cartelle sul Drive (cliente / progetto / sottocartelle del processo), se il Drive e' collegato in
        // scrittura. Se non riesce, il progetto c'e' lo stesso: si rifanno dal pulsante nella sua pagina.
        if (\App\Documents\DocumentService::isInstalled()) {
            $documents = $this->container->get(\App\Documents\DocumentService::class);
            if ($documents->isReady() && $documents->store()->canWrite()) {
                try {
                    $documents->ensureProjectFolders($id);
                } catch (\App\Connector\ConnectorException) {
                }
            }
        }
    }

    protected function componentsAfterCreate(int $id): array
    {
        $steps = count($this->container->get(TaskRepository::class)->findForEntity(WorkOrderService::ENTITY, $id));
        $message = $steps > 0
            ? 'Commessa creata con ' . F::plural($steps, 'fase', 'fasi') . ' dal modello: la prima e\' gia\' nelle attivita\'.'
            : 'Commessa creata. Aggiungi le fasi con "+ Fase".';

        return $this->container->get(EditWorkOrderTool::class)->buildPage($this->repository()->find($id), $message, 'success');
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $created = 0;
        if ($before instanceof WorkOrder && $before->workOrderTypeId === null) {
            $created = $this->service()->createStepsFromType($id);
        }
        $this->service()->refresh($id);

        return $created > 0 ? 'Salvato: aggiunte ' . F::plural($created, 'fase', 'fasi') . ' dal modello.' : null;
    }

    public function waitAction(): ?string
    {
        $id = (int) $this->param('id');
        $reason = trim((string) $this->request->get('reason', ''));
        $this->service()->setWaiting($id, $reason);

        return $this->reply($id, 'Segnata in attesa del cliente.');
    }

    /**
     * "Il cliente ha risposto": se la commessa era ferma su una fase che
     * aspettava il cliente, quella fase e' fatta e si passa alla prossima.
     */
    public function resumeAction(): ?string
    {
        $id = (int) $this->param('id');
        $tasks = $this->container->get(TaskRepository::class)->findForEntity(WorkOrderService::ENTITY, $id);
        $next = $this->service()->nextStep($tasks);

        if ($next !== null && (int) $next->waitsForClient === 1) {
            $this->container->get(TaskService::class)->complete((int) $next->id);
            $after = $this->service()->nextStep($this->container->get(TaskRepository::class)->findForEntity(WorkOrderService::ENTITY, $id));
            $this->service()->resume($id);
            $this->service()->refresh($id);

            return $this->reply($id, "Fatto: \u{ab}{$next->title}\u{bb}." . ($after !== null ? " Ora tocca a \u{ab}{$after->title}\u{bb}." : ''));
        }

        $this->service()->resume($id);

        return $this->reply($id, 'Ripresa: di nuovo in corso.');
    }

    public function pauseAction(): ?string
    {
        $id = (int) $this->param('id');
        $this->service()->pause($id);

        return $this->reply($id, 'Messa in pausa: non comparira\' tra le commesse ferme.');
    }

    /**
     * Un'idea della roadmap diventa un progetto: con il processo scelto
     * (fasi, squadra, cartelle, come "Nuovo progetto"), titolo e note
     * dall'idea; l'idea si collega al progetto e passa "in corso".
     */
    public function fromIdeaAction(): void
    {
        $note = $this->container->get(\App\Repository\CustomerNoteRepository::class)->find((int) $this->param('id'));
        $typeId = (int) $this->request->get('type_id', 0);
        $type = $typeId > 0 ? $this->container->get(\App\Repository\WorkOrderTypeRepository::class)->find($typeId) : null;
        if ($note === null || $note->customerId === null || (int) $note->onRoadmap !== 1) {
            $this->json(['ok' => false, 'message' => 'Idea non trovata.'], 404);

            return;
        }
        if ($type === null) {
            $this->json(['ok' => false, 'message' => 'Scegli il servizio (il processo) del progetto.'], 422);

            return;
        }
        $commercial = $this->container->get(\App\Work\ProcessService::class)->startsCommercial($typeId);
        $id = $this->repository()->insert([
            'customer_id' => $note->customerId,
            'work_order_type_id' => $typeId,
            'title' => mb_substr((string) $note->title, 0, 190),
            'stage' => $commercial ? 'contatto' : 'da avviare',
            'priority' => 'normale',
            'start_date' => date('Y-m-d'),
            'owner_user_id' => $this->container->get(\App\Service\AuthService::class)->currentUserId(),
            'notes' => $note->body,
        ]);
        $this->afterCreate($id);
        $this->container->get(\App\Knowledge\KnowledgeService::class)->linkIdeaToProject((int) $note->id, $id);
        $this->json(['ok' => true, 'open' => '/commesse/' . $id, 'message' => "Progetto creato dall'idea, con il processo \u{ab}{$type->name}\u{bb}."]);
    }

    public function deliverAction(): ?string
    {
        $id = (int) $this->param('id');
        $this->service()->deliver($id);

        return $this->reply($id, 'Consegnata. Bel lavoro!');
    }

    public function remindedAction(): ?string
    {
        $id = (int) $this->param('id');
        $this->service()->markReminded($id);

        return $this->reply($id, 'Segnato: cliente sollecitato oggi.');
    }

    /**
     * "Prepara il preventivo" dal progetto: nasce gia' collegato, con la
     * riga del servizio del processo se c'e'. Da qui le fasi commerciali
     * avanzano da sole (bozza, approvazione, invio, firma).
     */
    public function quoteAction(): ?string
    {
        $id = (int) $this->param('id');
        $order = $this->repository()->find($id);
        if (!$order instanceof WorkOrder || $order->customerId === null) {
            $this->json(['ok' => false, 'message' => 'Serve un cliente per il preventivo.'], 422);

            return null;
        }
        if ($order->quoteId !== null) {
            $this->json(['ok' => true, 'open' => '/preventivi/' . $order->quoteId]);

            return null;
        }
        $quotes = $this->container->get(\App\Repository\QuoteRepository::class);
        $quoteId = $quotes->insert([
            'customer_id' => $order->customerId,
            'title' => $order->title,
            'stage' => 'bozza',
            'quote_date' => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+30 days')),
            'owner_user_id' => $this->container->get(\App\Service\AuthService::class)->currentUserId(),
        ]);
        $type = $order->workOrderTypeId !== null ? $this->container->get(\App\Repository\WorkOrderTypeRepository::class)->find((int) $order->workOrderTypeId) : null;
        $service = $type instanceof \App\Model\WorkOrderType && $type->serviceId !== null && in_array('services', \App\Package\PackageCatalogService::installedNames(), true)
            ? $this->container->get(\App\Repository\ServiceRepository::class)->find((int) $type->serviceId)
            : null;
        if ($service !== null) {
            $this->container->get(\App\Repository\QuoteLineRepository::class)->insert([
                'quote_id' => $quoteId,
                'service_id' => $service->id,
                'description' => $service->name,
                'quantity' => 1,
                'unit_price' => $service->price ?? 0,
                'billing_type' => $service->billingType,
                'recurrence' => $service->recurrence,
                'position' => 1,
            ]);
        }
        $quoteService = $this->container->get(\App\Quotes\QuoteService::class);
        $quoteService->initialize($quoteId);
        $quoteService->recalc($quoteId);
        $this->container->get(\App\Work\ProcessService::class)->linkQuote($id, $quoteId);
        $this->json(['ok' => true, 'open' => '/preventivi/' . $quoteId, 'message' => 'Preventivo creato e collegato al progetto.']);

        return null;
    }

    /** Non acquisita: il cliente non ha accettato. Il motivo serve ai follow-up. */
    public function lostAction(): ?string
    {
        $id = (int) $this->param('id');
        $reason = trim((string) $this->request->get('reason', ''));
        $this->repository()->update($id, [
            'stage' => 'non acquisita',
            'lost_reason' => $reason !== '' ? mb_substr($reason, 0, 255) : null,
            'last_activity_at' => date('Y-m-d H:i:s'),
        ]);
        // L'idea della roadmap da cui era nato: scartata.
        if (\App\Knowledge\KnowledgeService::isInstalled()) {
            $this->container->get(\App\Knowledge\KnowledgeService::class)->syncRoadmap($id, 'scartata');
        }

        return $this->reply($id, 'Segnato come non acquisito.');
    }

    private function reply(int $id, string $message): ?string
    {
        return $this->renderPage(
            $this->container->get(EditWorkOrderTool::class)->buildPage($this->repository()->find($id), $message, 'success'),
            ['title' => $this->editPageTitle()]
        );
    }

    private function service(): WorkOrderService
    {
        return $this->container->get(WorkOrderService::class);
    }
}
