<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\DocumentRequestRepository;

final class EditDocumentRequestTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return DocumentRequestRepository::class;
    }

    protected function packageName(): string
    {
        return 'document_requests';
    }

    protected function entityTag(): string
    {
        return 'document_request';
    }

    protected function baseUrl(): string
    {
        return '/documenti-richiesti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova richiesta documento' : 'Modifica richiesta documento';
    }

    protected function searchColumns(): array
    {
        return ['document_type', 'notes'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'documentType', 'label' => 'Tipo documento'],
            ['key' => 'completenessStatus', 'label' => 'Stato completezza'],
        ];
    }

    public function name(): string
    {
        return 'edit_document_request';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un documento richiesto, cercato per id o per tipo documento/note. "
            . "Usalo quando l'utente chiede di aprire o modificare una richiesta documento specifica.";
    }

    public function requiredPermission(): ?string
    {
        return 'document_requests.edit';
    }
}
