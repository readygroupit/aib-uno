<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditDocumentRequestTool;
use App\Prompt\Tool\ListDocumentRequestsTool;
use App\Repository\DocumentRequestRepository;

final class DocumentRequestsController extends AbstractEntityController
{
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
}
