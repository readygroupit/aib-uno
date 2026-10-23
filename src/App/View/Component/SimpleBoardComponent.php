<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Una schermata semplice a sezioni di righe (titolo, dettaglio, stato,
 * azioni): per le cose piccole che non meritano una schermata propria
 * (ferie e permessi, spese). Renderer: simple-board.js.
 */
final class SimpleBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'simple-board',
            'title' => $config['title'] ?? '',
            'url' => $config['url'] ?? null,
            'createHref' => $config['createHref'] ?? null,
            'createLabel' => $config['createLabel'] ?? 'Nuovo',
            'stats' => $config['stats'] ?? [],
            'sections' => $config['sections'] ?? [],
        ];
    }
}
