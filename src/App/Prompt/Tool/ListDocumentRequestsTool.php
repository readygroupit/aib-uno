<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\DocumentRequestRepository;

final class ListDocumentRequestsTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return DocumentRequestRepository::class;
    }

    protected function entityTag(): string
    {
        return 'document_request';
    }

    protected function baseUrl(): string
    {
        return '/documenti-richiesti';
    }

    protected function title(): string
    {
        return 'Documenti richiesti';
    }

    protected function statLabel(): string
    {
        return 'Totale richieste';
    }

    protected function createLabel(): ?string
    {
        return 'Nuova richiesta';
    }

    protected function createPermission(): ?string
    {
        return 'document_requests.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'documentType', 'label' => 'Tipo documento'],
            ['key' => 'completenessStatus', 'label' => 'Stato completezza', 'badge' => true],
            ['key' => 'requestedAt', 'label' => 'Richiesto il'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/documenti-richiesti/{id}', 'permission' => 'document_requests.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_document_requests';
    }

    public function description(): string
    {
        return "Mostra l'elenco dei documenti richiesti per le pratiche, con statistica totale.";
    }

    public function menuLabel(): ?string
    {
        return 'Documenti richiesti';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function requiredPermission(): ?string
    {
        return 'document_requests.view';
    }

    public function triggers(): array
    {
        return ['documenti richiesti', 'lista documenti richiesti', 'elenco documenti richiesti', 'documenti mancanti'];
    }
}
