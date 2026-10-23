<?php

declare(strict_types=1);

namespace App\ClientRequests;

use App\Core\Container;
use App\Model\ClientRequest;
use App\Model\Customer;
use App\Model\Task;
use App\Package\PackageCatalogService;
use App\Repository\ClientRequestRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerTeamMemberRepository;
use App\Repository\InstallmentRepository;
use App\Repository\TaskRepository;
use App\Work\Workdays;

/**
 * Cosa succede a una richiesta del cliente:
 * - senza chi la fa, la si propone dalla squadra del cliente (social per
 *   modifiche e contenuti, sviluppo per l'assistenza tecnica, il
 *   referente per il resto); altrimenti resta da smistare;
 * - assegnata, diventa un'attivita' di quella persona (organizer), con la
 *   scadenza e la priorita'; l'attivita' fatta chiude la richiesta;
 * - un lavoro extra fatto si mette nello scadenzario con un clic.
 */
final class ClientRequestService
{
    public const ENTITY = 'client_requests';
    private const ROLE_FOR_KIND = ['modifica' => 'social', 'nuovo contenuto' => 'social', 'assistenza tecnica' => 'sviluppo'];

    private ClientRequestRepository $requests;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->requests = $container->get(ClientRequestRepository::class);
    }

    /** Dopo creazione o modifica: chi la fa e l'attivita' allineata. */
    public function sync(int $requestId): void
    {
        $request = $this->requests->find($requestId);
        if (!$request instanceof ClientRequest) {
            return;
        }
        if ($request->assignedUserId === null && $request->isOpen()) {
            $proposed = $this->proposedAssignee($request);
            if ($proposed !== null) {
                $this->requests->update($requestId, ['assigned_user_id' => $proposed]);
                $request = $this->requests->find($requestId);
            }
        }
        if ($request->assignedUserId !== null && $request->stage === 'nuova') {
            $this->requests->update($requestId, ['stage' => 'presa in carico']);
            $request = $this->requests->find($requestId);
        }
        $this->syncTask($request);
    }

    public function assign(int $requestId, int $userId): void
    {
        $this->requests->update($requestId, ['assigned_user_id' => $userId, 'stage' => 'presa in carico']);
        $this->sync($requestId);
    }

    public function complete(int $requestId, string $resolution = ''): ClientRequest
    {
        $request = $this->requests->find($requestId);
        if (!$request instanceof ClientRequest) {
            throw new \InvalidArgumentException('Richiesta non trovata.');
        }
        $this->requests->update($requestId, ['stage' => 'fatta', 'done_at' => date('Y-m-d H:i:s'), 'resolution' => trim($resolution) !== '' ? mb_substr(trim($resolution), 0, 255) : $request->resolution]);
        $task = $request->taskId !== null ? $this->container->get(TaskRepository::class)->find((int) $request->taskId) : null;
        if ($task instanceof Task && $task->isOpen()) {
            // Direttamente, non da TaskService::complete(): richiamerebbe questa stessa chiusura.
            $this->container->get(TaskRepository::class)->update((int) $task->id, ['stage' => 'fatta', 'completed_at' => date('Y-m-d H:i:s')]);
        }

        return $this->requests->find($requestId);
    }

    /** Chiamata da TaskService quando l'attivita' della richiesta e' fatta. */
    public function markDoneFromTask(int $requestId): void
    {
        $request = $this->requests->find($requestId);
        if ($request instanceof ClientRequest && $request->isOpen()) {
            $this->requests->update($requestId, ['stage' => 'fatta', 'done_at' => date('Y-m-d H:i:s')]);
        }
    }

    /** Il lavoro extra nello scadenzario (unica soluzione, a 30 giorni). */
    public function bill(int $requestId): int
    {
        $request = $this->requests->find($requestId);
        if (!$request instanceof ClientRequest || (int) $request->isExtra !== 1) {
            throw new \InvalidArgumentException('Non e\' un lavoro extra.');
        }
        if ($request->installmentId !== null) {
            return (int) $request->installmentId;
        }
        if ((float) $request->extraAmount <= 0) {
            throw new \InvalidArgumentException('Manca l\'importo dell\'extra: indicalo nella richiesta.');
        }
        if (!in_array('installments', PackageCatalogService::installedNames(), true)) {
            throw new \InvalidArgumentException('Lo scadenzario non e\' installato.');
        }
        $customer = $this->container->get(CustomerRepository::class)->find((int) $request->customerId);
        $id = $this->container->get(InstallmentRepository::class)->insert([
            'customer_id' => $request->customerId,
            'description' => mb_substr('Extra: ' . $request->summary, 0, 190),
            'kind' => 'unica soluzione',
            'amount' => $request->extraAmount,
            'due_date' => date('Y-m-d', strtotime('+' . (int) ($customer instanceof Customer && $customer->paymentTermsDays ? $customer->paymentTermsDays : 30) . ' days')),
            'stage' => 'da incassare',
            'payment_method' => $customer instanceof Customer && $customer->preferredPaymentMethod ? $customer->preferredPaymentMethod : 'bonifico',
        ]);
        $this->requests->update($requestId, ['installment_id' => $id]);

        return $id;
    }

    /** Chi la fa, dalla squadra del cliente; poi il referente interno del cliente. */
    public function proposedAssignee(ClientRequest $request): ?int
    {
        $team = [];
        foreach ($this->container->get(CustomerTeamMemberRepository::class)->findByCustomer((int) $request->customerId) as $member) {
            $team[(string) $member->role] ??= (int) $member->userId;
        }
        $role = self::ROLE_FOR_KIND[$request->kind] ?? 'referente';
        $customer = $this->container->get(CustomerRepository::class)->find((int) $request->customerId);

        return $team[$role] ?? $team['referente'] ?? ($customer instanceof Customer ? $customer->accountUserId : null);
    }

    private function syncTask(ClientRequest $request): void
    {
        $tasks = $this->container->get(TaskRepository::class);
        $task = $request->taskId !== null ? $tasks->find((int) $request->taskId) : null;
        if (!$request->isOpen()) {
            if ($task instanceof Task && $task->isOpen()) {
                $tasks->update((int) $task->id, $request->stage === 'annullata' ? ['stage' => 'annullata'] : ['stage' => 'fatta', 'completed_at' => date('Y-m-d H:i:s')]);
            }

            return;
        }
        if ($request->assignedUserId === null) {
            return;
        }
        $customer = $this->container->get(CustomerRepository::class)->find((int) $request->customerId);
        $data = [
            'title' => ($customer instanceof Customer ? $customer->displayName() . ': ' : '') . $request->summary,
            'description' => $request->details,
            'planned_date' => Workdays::adjust($request->dueDate !== null && $request->dueDate > date('Y-m-d') ? $request->dueDate : date('Y-m-d')),
            'due_at' => $request->dueDate !== null ? $request->dueDate . ' 18:00:00' : null,
            'priority' => $request->priority ?: 'normale',
            'estimated_minutes' => $request->estimatedMinutes ?: 30,
            'assigned_user_id' => $request->assignedUserId,
        ];
        if ($task instanceof Task) {
            $tasks->update((int) $task->id, $data);

            return;
        }
        $taskId = $tasks->insert($data + ['stage' => 'da fare', 'waits_for_client' => 0, 'entity_type' => self::ENTITY, 'entity_id' => $request->id]);
        $this->requests->update((int) $request->id, ['task_id' => $taskId]);
    }
}
