<?php

declare(strict_types=1);

namespace App\View\Component;

/** Un preventivo: righe, totali, condizioni, cosa manca e il passo successivo. */
final class QuoteSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'quote-summary', 'url' => $config['url'] ?? null] + ($config['quote'] ?? []);
    }
}
