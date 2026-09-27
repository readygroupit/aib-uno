<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Box statistica con sparkline opzionale. I valori grezzi della sparkline
 * viaggiano cosi' come sono: e' il renderer JS a calcolare i punti SVG
 * (stessa formula che prima stava in PHP, spostata lato client insieme
 * al resto del disegno).
 */
final class StatBoxComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'stat-box',
            'label' => $config['label'] ?? '',
            'value' => $config['value'] ?? '',
            'delta' => $config['delta'] ?? null,
            'deltaPositive' => $config['deltaPositive'] ?? true,
            'comparison' => $config['comparison'] ?? null,
            'sparkline' => $config['sparkline'] ?? null,
        ];
    }
}
