<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Media\MediaOverviewService;
use App\Model\AbstractModel;
use App\Model\MediaAsset;
use App\Repository\MediaAssetRepository;
use App\View\Component\MediaSummaryComponent;

/** Un file dell'archivio: cosa contiene; descrizione e parole chiave si correggono a mano. */
final class EditMediaAssetTool extends AbstractEditEntityTool
{
    private MediaOverviewService $overview;
    private MediaSummaryComponent $summary;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->overview = $container->get(MediaOverviewService::class);
        $this->summary = $container->get(MediaSummaryComponent::class);
    }

    protected function repositoryClass(): string
    {
        return MediaAssetRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'media_assets';
    }

    protected function packageName(): string
    {
        return 'media_library';
    }

    protected function entityTag(): string
    {
        return 'media_asset';
    }

    protected function baseUrl(): string
    {
        return '/materiali';
    }

    protected function formTitle(bool $isNew): string
    {
        return 'Correggi la descrizione';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Correggi la descrizione';
    }

    protected function searchColumns(): array
    {
        return ['name', 'description', 'tags'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'name', 'label' => 'File'], ['key' => 'folder', 'label' => 'Cartella']];
    }

    protected function systemManagedFields(): array
    {
        return ['source', 'external_id', 'name', 'kind', 'mime_type', 'folder', 'web_link', 'size_bytes', 'width', 'height', 'duration_seconds', 'modified_at', 'analysis_status', 'analysis_error', 'orientation', 'analysis', 'thumb_path', 'analyzed_at'];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof MediaAsset) {
            return [];
        }
        $actions = [];
        if ($this->auth->hasPermission('media_library.edit')) {
            $actions[] = ['label' => 'Rifai la descrizione', 'action' => '/materiali/' . $model->id . '/analizza', 'variant' => 'secondary', 'icon' => 'wand-magic-sparkles', 'confirm' => $model->analysisStatus === 'analizzato' ? 'Rifare la descrizione con l\'IA? Le correzioni a mano si perdono.' : null];
        }

        return [$this->summary->toData(['url' => '/materiali/' . $model->id, 'asset' => $this->overview->detail($model), 'actions' => $actions])];
    }

    public function name(): string
    {
        return 'edit_media_asset';
    }

    public function description(): string
    {
        return "Apre un file dell'archivio foto e video (cercato per id o nome): anteprima, descrizione dell'IA, momenti del video.";
    }

    public function requiredPermission(): ?string
    {
        return 'media_library.view';
    }
}
