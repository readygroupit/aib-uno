<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * "Attivita' recente" - le ultime righe create/modificate DAVVERO da
 * questo utente in giro per il sistema (vedi ShowProfileTool), non un
 * log finto: derivata da created_at/created_by, gia' presenti su ogni
 * tabella, non serve una tabella di audit dedicata per questo.
 */
final class ActivityFeedComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'activity-feed',
            'title' => $config['title'] ?? '',
            'subtitle' => $config['subtitle'] ?? '',
            'emptyMessage' => $config['emptyMessage'] ?? 'Nessuna attivita\' recente.',
            // [{label, detail, timestamp, dotColor, href}]
            'items' => $config['items'] ?? [],
        ];
    }
}
