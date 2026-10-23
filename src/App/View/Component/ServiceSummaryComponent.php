<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Testata di un servizio venduto: cliente, prezzo, piano, rinnovo, le sue
 * righe di scadenzario e le azioni (rinnova ora, ricrea le scadenze).
 */
final class ServiceSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'service-summary',
            'url' => $config['url'] ?? null,
            'service' => $config['service'] ?? [],
            'customer' => $config['customer'] ?? null,
            'installments' => $config['installments'] ?? [],
            'actions' => $config['actions'] ?? [],
        ];
    }
}
