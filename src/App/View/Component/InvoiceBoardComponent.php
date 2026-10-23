<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Fatture per cosa c'e' da fare (da fatturare, bozze, da inviare allo
 * SdI, da incassare) e le ultime pagate. Dati da
 * App\Invoicing\InvoiceOverviewService::board().
 */
final class InvoiceBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'invoice-board',
            'title' => $config['title'] ?? 'Fatture',
            'url' => $config['url'] ?? '/fatture',
            'stats' => $config['stats'] ?? [],
            'groups' => $config['groups'] ?? [],
            'recentPaid' => $config['recentPaid'] ?? [],
            'links' => $config['links'] ?? [],
        ];
    }
}
