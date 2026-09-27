<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Griglia di box grandi al posto di una sidebar - ogni box porta una
 * frase suggerita ('prompt') che il click reinvia allo stesso canale del
 * prompt scritto/parlato: stesso principio "un'azione raggiungibile in
 * piu' modi, stessa logica".
 */
final class MenuGridComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'menu-grid',
            'title' => $config['title'] ?? 'Menu',
            'items' => $config['items'] ?? [],
            'sections' => $config['sections'] ?? [],
        ];
    }
}
