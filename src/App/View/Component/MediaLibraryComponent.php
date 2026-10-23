<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * L'archivio foto e video: casella di ricerca a parole, filtri veloci, i
 * risultati come anteprime con il perche', lo stato dell'indice.
 */
final class MediaLibraryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'media-library',
            'title' => $config['title'] ?? 'Archivio foto e video',
            'url' => $config['url'] ?? '/materiali',
            'query' => $config['query'] ?? '',
            'filters' => $config['filters'] ?? [],
            'understood' => $config['understood'] ?? null,
            'cards' => $config['cards'] ?? [],
            'status' => $config['status'] ?? [],
            'actions' => $config['actions'] ?? [],
            'empty' => $config['empty'] ?? null,
            'setupHref' => $config['setupHref'] ?? null,
        ];
    }
}
