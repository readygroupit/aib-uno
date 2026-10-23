<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Media\LocalFolderSource;
use App\Media\MediaAnalyzer;
use App\Media\MediaLibraryService;
use App\Model\MediaAsset;
use App\Prompt\Tool\EditMediaAssetTool;
use App\Prompt\Tool\ShowMediaLibraryTool;
use App\Repository\MediaAssetRepository;
use App\Service\AuthService;

/**
 * Archivio foto e video: ricerca (?q=), scheda del file, anteprime e file
 * locali serviti solo a chi e' collegato, e le azioni (leggi i file nuovi,
 * descrivi ora, rifai la descrizione).
 */
final class MediaController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return MediaAssetRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditMediaAssetTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowMediaLibraryTool::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'media_assets';
    }

    protected function packageName(): string
    {
        return 'media_library';
    }

    protected function listPageTitle(): string
    {
        return 'Archivio foto e video';
    }

    protected function newPageTitle(): string
    {
        return 'Archivio foto e video';
    }

    protected function editPageTitle(): string
    {
        return 'File dell\'archivio';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'index', 'edit', 'thumb', 'file' => 'media_library.view',
            default => 'media_library.edit',
        };
    }

    public function indexAction(): ?string
    {
        $components = $this->container->get(ShowMediaLibraryTool::class)->execute([
            'query' => (string) $this->request->get('q', ''),
            'tipo' => (string) $this->request->get('tipo', ''),
            'cliente' => (int) $this->request->get('cliente', 0),
            'senza_persone' => $this->request->get('persone') === '0',
        ]);

        return $this->renderPage($components, ['title' => $this->listPageTitle()]);
    }

    /** Vedere la scheda basta il permesso di consultazione; correggerla no. */
    public function editAction(): ?string
    {
        if ($this->request->getMethod() === 'POST' && !$this->container->get(AuthService::class)->hasPermission('media_library.edit')) {
            $this->json(['ok' => false, 'message' => 'Non puoi correggere l\'archivio.'], 403);

            return null;
        }

        return parent::editAction();
    }

    public function thumbAction(): ?string
    {
        $asset = $this->repository()->find((int) $this->param('id'));
        $file = $asset instanceof MediaAsset && $asset->thumbPath !== null ? MediaAnalyzer::thumbDir() . '/' . basename($asset->thumbPath) : null;
        if ($file === null || !is_file($file)) {
            $this->response->setStatusCode(404);

            return '';
        }
        $this->response->addHeader('Content-Type', 'image/jpeg');
        // L'URL cambia con la data dell'analisi (?v=): si puo' tenere in cache a lungo.
        $this->response->addHeader('Cache-Control', 'private, max-age=2592000');

        return (string) file_get_contents($file);
    }

    /** Il file vero, solo per l'archivio su cartella (quello su Drive si apre su Drive). */
    public function fileAction(): ?string
    {
        $asset = $this->repository()->find((int) $this->param('id'));
        $path = $asset instanceof MediaAsset && $asset->source === 'cartella' ? $this->container->get(LocalFolderSource::class)->path($asset) : null;
        if ($path === null) {
            $this->response->setStatusCode(404);

            return '';
        }
        $this->response->addHeader('Content-Type', (string) ($asset->mimeType ?: 'application/octet-stream'));
        $this->response->addHeader('Content-Disposition', 'inline; filename="' . str_replace('"', '', (string) $asset->name) . '"');

        return (string) file_get_contents($path);
    }

    public function syncAction(): void
    {
        $outcome = $this->container->get(MediaLibraryService::class)->sync();
        $this->json(['ok' => true, 'message' => "Archivio letto: {$outcome['added']} nuovi, {$outcome['changed']} cambiati, {$outcome['removed']} tolti."]);
    }

    /** Una manciata di descrizioni subito (il resto lo fa il job, a gruppi). */
    public function describeAction(): void
    {
        set_time_limit(300);
        $outcome = $this->container->get(MediaLibraryService::class)->analyzePending(10);
        $this->json(['ok' => $outcome['failed'] === 0, 'message' => "Descritti {$outcome['done']} file" . ($outcome['failed'] > 0 ? ", {$outcome['failed']} non riusciti" : '') . ($outcome['left'] > 0 ? ", ne restano {$outcome['left']} (li fa il job)." : '.')]);
    }

    public function analyzeAction(): void
    {
        set_time_limit(180);
        $asset = $this->repository()->find((int) $this->param('id'));
        if (!$asset instanceof MediaAsset) {
            $this->json(['ok' => false, 'message' => 'File non trovato.'], 404);

            return;
        }
        $ok = $this->container->get(MediaLibraryService::class)->analyze($asset);
        $this->json(['ok' => $ok, 'message' => $ok ? 'Descrizione rifatta.' : 'Non riuscito: ' . ($this->repository()->find((int) $asset->id)?->analysisError ?? 'errore')]);
    }
}
