<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Clienti per temperatura: prima chi rischia, con il perche' e cosa fare;
 * di lato gli ultimi contatti. Dati da HealthOverviewService::board().
 */
final class ClientHealthComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'client-health',
            'title' => $config['title'] ?? 'Temperatura dei clienti',
            'url' => $config['url'] ?? '/clienti/temperatura',
            'stats' => $config['stats'] ?? [],
            'rows' => $config['rows'] ?? [],
            'recent' => $config['recent'] ?? [],
            'createHref' => $config['createHref'] ?? null,
        ];
    }
}
