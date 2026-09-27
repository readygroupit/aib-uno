<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditDocumentRequestTool;
use App\Prompt\Tool\ListDocumentRequestsTool;
use App\Repository\CommunicationContentRepository;
use App\Repository\CommunicationRepository;
use App\Repository\DocumentRequestRepository;

final class DocumentRequestsController extends AbstractEntityController
{
    protected function requiredPermissionForAction(string $action): ?string
    {
        return $action === 'remind' ? 'document_requests.edit' : parent::requiredPermissionForAction($action);
    }

    protected function repositoryClass(): string
    {
        return DocumentRequestRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditDocumentRequestTool::class;
    }

    protected function listToolClass(): string
    {
        return ListDocumentRequestsTool::class;
    }

    protected function packageName(): string
    {
        return 'document_requests';
    }

    protected function listPageTitle(): string
    {
        return 'Documenti richiesti';
    }

    protected function newPageTitle(): string
    {
        return 'Nuova richiesta documento';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica richiesta documento';
    }

    protected function defaultsOnCreate(): array
    {
        return ['completeness_status' => 'mancante'];
    }

    protected function createdMessage(): string
    {
        return 'Richiesta documento creata.';
    }

    /**
     * Azione "Approva" della card "Documenti mancanti" nel cruscotto home
     * (vedi ShowHomeDashboardTool/ApprovalQueueComponent): registra
     * davvero un sollecito in Comunicazioni (canale email, verso la
     * pratica collegata) invece di limitarsi a segnare uno stato - stesso
     * principio di LeadsController::markContactedAction(), un'azione reale
     * anche se semplice, non un bottone finto.
     */
    public function remindAction(): void
    {
        $id = (int) $this->param('id');
        $documentRequest = $this->repository()->find($id);

        if ($documentRequest === null) {
            $this->json(['ok' => false], 404);

            return;
        }

        /** @var CommunicationRepository $communications */
        $communications = $this->container->get(CommunicationRepository::class);
        /** @var CommunicationContentRepository $communicationContents */
        $communicationContents = $this->container->get(CommunicationContentRepository::class);

        $communicationId = $communications->insert([
            'entity_type' => 'cases',
            'entity_id' => (string) $documentRequest->caseId,
            'channel' => 'email',
            'direction' => 'out',
            'delivery_status' => 'inviato',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
        $communicationContents->insert([
            'communication_id' => (string) $communicationId,
            'subject' => 'Sollecito documenti mancanti',
            'body' => 'Sollecito automatico per: ' . ($documentRequest->documentType ?? 'documento richiesto') . '.',
        ]);

        $this->json(['ok' => true]);
    }
}
