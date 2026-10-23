<?php

declare(strict_types=1);

namespace App\View\Component;

/** Un file dell'archivio: anteprima grande, descrizione, cosa contiene, i momenti del video. */
final class MediaSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'media-summary', 'url' => $config['url'] ?? null, 'actions' => $config['actions'] ?? []] + ($config['asset'] ?? []);
    }
}
