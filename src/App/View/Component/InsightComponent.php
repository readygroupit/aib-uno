<?php

declare(strict_types=1);

namespace App\View\Component;

/** "In sintesi": 1-3 osservazioni brevi su quello che mostra la pagina (insight.js). */
final class InsightComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'insight',
            'title' => (string) ($config['title'] ?? 'In sintesi'),
            'points' => array_values(array_map(static fn ($p) => mb_substr((string) $p, 0, 240), array_slice((array) ($config['points'] ?? []), 0, 3))),
        ];
    }
}
