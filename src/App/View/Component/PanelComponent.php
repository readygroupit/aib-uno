<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Riquadro che tiene insieme elementi affini (es. due ripartizioni, grafico
 * e tabella dello stesso tema), affiancati: panel.js. 'children' sono gia'
 * dati di componenti (toData()).
 */
final class PanelComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'panel',
            'eyebrow' => $config['eyebrow'] ?? null,
            'title' => (string) ($config['title'] ?? ''),
            'subtitle' => $config['subtitle'] ?? null,
            'children' => array_values($config['children'] ?? []),
        ];
    }
}
