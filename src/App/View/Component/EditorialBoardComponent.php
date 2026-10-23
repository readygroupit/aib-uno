<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Piano editoriale: due settimane, una riga per cliente, i contenuti nei
 * giorni; sopra i numeri e i buchi rispetto al contratto. Dati da
 * App\Editorial\EditorialOverviewService::board().
 */
final class EditorialBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'editorial-board',
            'title' => $config['title'] ?? 'Piano editoriale',
            'url' => $config['url'] ?? '/piano-editoriale',
            'period' => $config['period'] ?? '',
            'prevHref' => $config['prevHref'] ?? null,
            'nextHref' => $config['nextHref'] ?? null,
            'todayHref' => $config['todayHref'] ?? null,
            'stats' => $config['stats'] ?? [],
            'days' => $config['days'] ?? [],
            'rows' => $config['rows'] ?? [],
            'gaps' => $config['gaps'] ?? [],
            'review' => $config['review'] ?? [],
            'createHref' => $config['createHref'] ?? null,
            'quotasHref' => $config['quotasHref'] ?? null,
        ];
    }
}
