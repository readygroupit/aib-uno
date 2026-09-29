<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Intestazione del cruscotto home: data, saluto e le tab di intervallo
 * (Oggi/7 giorni/30 giorni) - vedi ShowHomeDashboardTool. Chi e'
 * l'utente collegato vive nel menu profilo (vedi hero.js/window.UNO_USER),
 * non piu' qui: duplicava la stessa informazione in due posti diversi
 * (segnalato dall'utente). Tab come link veri (query string ?range=),
 * non fetch client: ricarica la pagina, stesso principio gia' in uso
 * ovunque nel framework (GET mostra, mai stato nascosto solo lato client).
 */
final class DashboardHeaderComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'dashboard-header',
            'dateLabel' => $config['dateLabel'] ?? '',
            'greeting' => $config['greeting'] ?? '',
            // [{label, value, active, href}]
            'rangeTabs' => $config['rangeTabs'] ?? [],
        ];
    }
}
