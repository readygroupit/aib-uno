<?php

declare(strict_types=1);

namespace Index\Controller;

use App\ClientRequests\ClientRequestService;
use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Prompt\Tool\EditClientRequestTool;
use App\Prompt\Tool\ShowClientRequestsTool;
use App\Repository\ClientRequestRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;

/**
 * Richieste dei clienti. Dopo ogni salvataggio: chi la fa (dalla squadra
 * del cliente) e la sua attivita'. Azioni a un clic: prendo io, fatta,
 * nello scadenzario.
 */
final class ClientRequestsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return ClientRequestRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditClientRequestTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowClientRequestsTool::class;
    }

    protected function packageName(): string
    {
        return 'client_requests';
    }

    protected function listPageTitle(): string
    {
        return 'Richieste dei clienti';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova richiesta';
    }

    protected function editPageTitle(): string
    {
        return 'Richiesta del cliente';
    }

    protected function defaultsOnCreate(): array
    {
        return [
            'received_at' => date('Y-m-d H:i:s'),
            'received_by_user_id' => (string) $this->container->get(AuthService::class)->currentUserId(),
            'channel' => 'telefono',
            'kind' => 'modifica',
            'priority' => 'normale',
            'stage' => 'nuova',
            'is_extra' => '0',
        ];
    }

    protected function createdMessage(): string
    {
        return 'Richiesta segnata.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'take', 'complete' => 'client_requests.edit',
            'bill' => 'installments.create',
            default => parent::requiredPermissionForAction($action),
        };
    }

    protected function afterCreate(int $id): void
    {
        $this->container->get(ClientRequestService::class)->sync($id);
    }

    protected function componentsAfterCreate(int $id): array
    {
        $request = $this->repository()->find($id);
        $who = $request?->assignedUserId !== null ? $this->container->get(UserRepository::class)->find((int) $request->assignedUserId) : null;
        $message = $who !== null
            ? 'Richiesta segnata: la trova ' . trim("{$who->firstName} {$who->lastName}") . ' tra le sue attivita\'.'
            : 'Richiesta segnata: e\' tra quelle da smistare.';

        return $this->container->get(EditClientRequestTool::class)->buildPage($request, $message, 'success');
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $this->container->get(ClientRequestService::class)->sync($id);

        return null;
    }

    public function takeAction(): void
    {
        $this->container->get(ClientRequestService::class)->assign((int) $this->param('id'), (int) $this->container->get(AuthService::class)->currentUserId());
        $this->json(['ok' => true, 'message' => 'Presa in carico: e\' tra le tue attivita\'.']);
    }

    public function completeAction(): void
    {
        try {
            $request = $this->container->get(ClientRequestService::class)->complete((int) $this->param('id'), (string) $this->request->get('text', ''));
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => (int) $request->isExtra === 1 ? 'Fatta. E\' un extra: mettilo nello scadenzario.' : 'Fatta.']);
    }

    public function billAction(): void
    {
        try {
            $installmentId = $this->container->get(ClientRequestService::class)->bill((int) $this->param('id'));
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);

            return;
        }
        $this->json(['ok' => true, 'message' => 'Extra nello scadenzario.', 'open' => '/scadenzario/' . $installmentId]);
    }
}
