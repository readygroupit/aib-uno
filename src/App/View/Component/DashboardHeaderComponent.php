<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Intestazione del cruscotto home: data, saluto, chi e' l'utente
 * collegato (iniziali/nome/profilo) e le tab di intervallo (Oggi/7
 * giorni/30 giorni) - vedi ShowHomeDashboardTool. Tab come link veri
 * (query string ?range=), non fetch client: ricarica la pagina, stesso
 * principio gia' in uso ovunque nel framework (GET mostra, mai stato
 * nascosto solo lato client).
 */
final class DashboardHeaderComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'dashboard-header',
            'dateLabel' => $config['dateLabel'] ?? '',
            'greeting' => $config['greeting'] ?? '',
            'userInitials' => $config['userInitials'] ?? '',
            'userName' => $config['userName'] ?? '',
            'userRole' => $config['userRole'] ?? '',
            // [{label, value, active, href}]
            'rangeTabs' => $config['rangeTabs'] ?? [],
        ];
    }
}
