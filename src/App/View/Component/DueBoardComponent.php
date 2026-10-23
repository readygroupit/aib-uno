<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Scadenzario: incassi raggruppati per urgenza (scaduti, questa
 * settimana, entro 30 e 90 giorni), rinnovi vicini e ultimi incassati,
 * con le azioni "incassato" e "rinnova". Dati da
 * App\Billing\AccountOverviewService::dueBoard().
 */
final class DueBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'due-board',
            'title' => $config['title'] ?? 'Scadenzario',
            'url' => $config['url'] ?? '/scadenzario',
            'createHref' => $config['createHref'] ?? null,
            'createLabel' => $config['createLabel'] ?? null,
            'stats' => $config['stats'] ?? [],
            'groups' => $config['groups'] ?? [],
            'renewals' => $config['renewals'] ?? [],
            'recentPaid' => $config['recentPaid'] ?? [],
        ];
    }
}
