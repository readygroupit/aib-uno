<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Ciclo di vita lead -> pratica -> rimborso come imbuto (ogni tappa una
 * barra larga in proporzione alla prima) - vedi ShowHomeDashboardTool per
 * come si calcolano le tappe. La larghezza delle barre si calcola lato
 * client (stessa scelta gia' fatta per la sparkline in stat-box.js): qui
 * arrivano solo i valori grezzi.
 */
final class FunnelComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'funnel',
            'title' => $config['title'] ?? '',
            'subtitle' => $config['subtitle'] ?? '',
            'summaryLabel' => $config['summaryLabel'] ?? null,
            'summaryValue' => $config['summaryValue'] ?? null,
            // [{label, value, caption, href?}]
            'stages' => $config['stages'] ?? [],
        ];
    }
}
