<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Documents\DocumentService;
use App\Model\AbstractModel;
use App\Model\Document;
use App\Repository\DocumentRepository;

/** Un file dei Documenti: cosa contiene e le parole chiave per ritrovarlo; il file vero si apre sul Drive. */
final class EditDocumentTool extends AbstractEditEntityTool
{
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
    }

    protected function repositoryClass(): string
    {
        return DocumentRepository::class;
    }

    protected function packageName(): string
    {
        return 'documents';
    }

    protected function manifestEntityKey(): string
    {
        return 'documents';
    }

    protected function entityTag(): string
    {
        return 'document';
    }

    protected function baseUrl(): string
    {
        return '/documenti/file';
    }

    protected function formTitle(bool $isNew): string
    {
        return 'File';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Nome, cosa contiene, parole chiave';
    }

    protected function searchColumns(): array
    {
        return ['name', 'description', 'tags'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'name', 'label' => 'File']];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof Document) {
            return [];
        }
        $service = $this->container->get(DocumentService::class);
        $path = $service->pathOf($model);

        return [[
            'type' => 'document-file',
            'name' => (string) $model->name,
            'path' => $path,
            'size' => $model->sizeBytes !== null ? DocumentService::size((int) $model->sizeBytes) : null,
            'kind' => DocumentService::kind((string) $model->mimeType),
            'open' => $model->webLink,
            'folderHref' => $model->folderId !== null ? '/documenti/cartella/' . $model->folderId : null,
            'origin' => $model->origin === 'drive' ? 'arrivato dal Drive' : 'caricato dal gestionale',
        ]];
    }

    public function name(): string
    {
        return 'edit_document';
    }

    public function description(): string
    {
        return 'Apre un file dei Documenti (sul Drive): nome, descrizione, parole chiave.';
    }

    public function requiredPermission(): ?string
    {
        return 'documents.view';
    }
}
