<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorException;
use App\Controller\AbstractEntityController;
use App\Documents\DocumentService;
use App\Model\AbstractModel;
use App\Model\Document;
use App\Prompt\Tool\EditDocumentTool;
use App\Prompt\Tool\SearchDocumentsTool;
use App\Repository\DocumentRepository;

/**
 * Documenti: le cartelle e i file del Drive dal gestionale. Le azioni sul
 * Drive rispondono in JSON ({ok, message, open}); la pagina di un file e'
 * il form di sempre (descrizione e parole chiave, il nome cambia anche sul
 * Drive). "Elimina" non cancella mai per sempre: cestino del Drive.
 */
final class DocumentsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return DocumentRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditDocumentTool::class;
    }

    protected function listToolClass(): string
    {
        return SearchDocumentsTool::class;
    }

    protected function packageName(): string
    {
        return 'documents';
    }

    protected function listPageTitle(): string
    {
        return 'Documenti';
    }

    protected function newPageTitle(): string
    {
        return 'Documenti';
    }

    protected function editPageTitle(): string
    {
        return 'File';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'index', 'folder', 'search' => 'documents.view',
            // Scaricare: chi vede i documenti o chi lavora alle fasi (controllo nell'azione).
            'download' => null,
            'newFolder', 'upload', 'projectFolders', 'customerFolder' => 'documents.create',
            'rename', 'sync', 'edit' => 'documents.edit',
            'trashFolder', 'trashFile', 'delete' => 'documents.delete',
            'new' => 'documents.create',
            default => null,
        };
    }

    public function indexAction(): ?string
    {
        return $this->renderPage([$this->service()->folderView(null)], ['title' => 'Documenti']);
    }

    public function folderAction(): ?string
    {
        return $this->renderPage([$this->service()->folderView((int) $this->param('id'))], ['title' => 'Documenti']);
    }

    /** Il form "nuovo" non serve: i file si caricano in una cartella. */
    public function newAction(): ?string
    {
        $this->redirect('/documenti');

        return null;
    }

    public function newFolderAction(): void
    {
        $this->drive(fn () => $this->service()->createFolder((int) $this->param('id'), (string) $this->request->get('reason', $this->request->get('name', ''))), 'Cartella creata, anche sul Drive.');
    }

    public function uploadAction(): void
    {
        $file = $this->request->file('file');
        if ($file === null) {
            $this->json(['ok' => false, 'message' => 'Nessun file arrivato (troppo grande per il server?).'], 422);

            return;
        }
        $this->drive(fn () => $this->service()->upload(
            (int) $this->param('id'),
            (string) $file['tmp_name'],
            (string) $file['name'],
            (string) ($file['type'] ?? ''),
            (string) $this->request->get('description', ''),
            (string) $this->request->get('tags', '')
        ), 'Caricato sul Drive.');
    }

    public function renameAction(): void
    {
        $name = trim((string) $this->request->get('reason', ''));
        if ($name === '') {
            $this->json(['ok' => false, 'message' => 'Scrivi il nuovo nome.'], 422);

            return;
        }
        $this->drive(fn () => $this->service()->renameFolder((int) $this->param('id'), $name), 'Rinominata, anche sul Drive.');
    }

    public function trashFolderAction(): void
    {
        try {
            $folder = $this->service()->trashFolder((int) $this->param('id'));
        } catch (ConnectorException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 502);

            return;
        }
        $this->json($folder !== null
            ? ['ok' => true, 'message' => "Nel cestino del Drive, con quello che conteneva: si recupera da li' per 30 giorni.", 'open' => '/documenti/cartella/' . $folder->parentId]
            : ['ok' => false, 'message' => 'Questa cartella non si puo\' eliminare.'], $folder !== null ? 200 : 422);
    }

    public function trashFileAction(): void
    {
        $this->drive(fn () => $this->service()->trashFile((int) $this->param('id')), "Nel cestino del Drive: si recupera da li' per 30 giorni.");
    }

    /** "Elimina" dal form del file: cestino del Drive, poi la sua cartella. */
    public function deleteAction(): ?string
    {
        $file = $this->repository()->find((int) $this->param('id'));
        try {
            $this->service()->trashFile((int) $this->param('id'));
        } catch (ConnectorException $e) {
            return $this->renderPage([['type' => 'notice', 'tone' => 'error', 'text' => $e->getMessage()]], ['title' => 'Documenti']);
        }

        return $this->renderPage([
            ['type' => 'notice', 'tone' => 'success', 'text' => "Nel cestino del Drive: si recupera da li' per 30 giorni."],
            $this->service()->folderView($file instanceof Document ? $file->folderId : null),
        ], ['title' => 'Documenti']);
    }

    /** Il nome cambiato nel form cambia anche sul Drive. */
    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $file = $this->repository()->find($id);
        if ($file instanceof Document && $before instanceof Document && $file->name !== $before->name) {
            try {
                $this->service()->renameFile($id, (string) $file->name);
            } catch (ConnectorException $e) {
                $this->repository()->update($id, ['name' => $before->name]);

                return 'Salvato, ma il nome non e\' cambiato sul Drive: ' . $e->getMessage();
            }
        }

        return null;
    }

    public function projectFoldersAction(): void
    {
        $this->drive(fn () => $this->service()->ensureProjectFolders((int) $this->param('id')), 'Cartelle create sul Drive.', true);
    }

    public function customerFolderAction(): void
    {
        $this->drive(fn () => $this->service()->ensureCustomerFolder((int) $this->param('id')), 'Cartella del cliente pronta sul Drive.', true);
    }

    public function syncAction(): void
    {
        try {
            $result = $this->service()->sync();
        } catch (ConnectorException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 502);

            return;
        }
        $this->json(['ok' => true, 'message' => "Letto dal Drive: {$result['folders']} cartelle, {$result['files']} file, {$result['trashed']} tolti."]);
    }

    public function searchAction(): ?string
    {
        return $this->renderPage($this->container->get(SearchDocumentsTool::class)->execute(['query' => (string) $this->request->get('q', '')]), ['title' => 'Documenti']);
    }

    /** Un'azione sul Drive: se il Drive dice di no, lo si dice e qui non cambia niente. */
    private function drive(callable $do, string $message, bool $openFolder = false): void
    {
        try {
            $result = $do();
        } catch (ConnectorException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 502);

            return;
        }
        $reply = ['ok' => true, 'message' => $message];
        if ($openFolder && $result instanceof \App\Model\DocumentFolder) {
            $reply['open'] = '/documenti/cartella/' . $result->id;
        }
        $this->json($reply);
    }

    private function service(): DocumentService
    {
        return $this->container->get(DocumentService::class);
    }

    /**
     * Scarica un file: quelli ancora in attesa del Drive dal gestionale
     * (storage/uploads, mai dalla cartella pubblica), gli altri dal Drive.
     */
    public function downloadAction(): ?string
    {
        $auth = $this->container->get(\App\Service\AuthService::class);
        if ($auth->currentUserId() === null || (!$auth->hasPermission('documents.view') && !$auth->hasPermission('tasks.edit'))) {
            $this->response->setStatusCode(403);

            return 'Accesso negato.';
        }
        $document = $this->container->get(\App\Repository\DocumentRepository::class)->find((int) $this->param('id'));
        if ($document === null) {
            $this->response->setStatusCode(404);

            return 'File non trovato.';
        }
        if ($document->localPath === null || !is_file((string) $document->localPath)) {
            if ($document->webLink) {
                $this->redirect((string) $document->webLink);

                return null;
            }
            $this->response->setStatusCode(404);

            return 'File non trovato.';
        }
        $root = realpath(ROOT_PATH . '/storage/uploads');
        $path = realpath((string) $document->localPath);
        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            $this->response->setStatusCode(404);

            return 'File non trovato.';
        }
        $this->response->addHeader('Content-Type', (string) ($document->mimeType ?: 'application/octet-stream'));
        $this->response->addHeader('Content-Disposition', 'inline; filename="' . str_replace('"', '', (string) $document->name) . '"');
        $this->response->addHeader('X-Content-Type-Options', 'nosniff');

        return (string) file_get_contents($path);
    }
}
