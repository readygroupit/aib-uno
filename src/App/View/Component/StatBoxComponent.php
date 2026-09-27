<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Box statistica con sparkline opzionale. I valori grezzi della sparkline
 * viaggiano cosi' come sono: e' il renderer JS a calcolare i punti SVG
 * (stessa formula che prima stava in PHP, spostata lato client insieme
 * al resto del disegno).
 */
final class StatBoxComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'stat-box',
            'label' => $config['label'] ?? '',
            'value' => $config['value'] ?? '',
            'delta' => $config['delta'] ?? null,
            'deltaPositive' => $config['deltaPositive'] ?? true,
            'comparison' => $config['comparison'] ?? null,
            'sparkline' => $config['sparkline'] ?? null,
            // Se presente, l'intera card diventa un link (es. le card del
            // cruscotto home: "conta e clicca per vedere", non solo un
            // numero statico) - navigazione normale del browser, stessa
            // pagina di destinazione che si raggiungerebbe dal menu.
            'href' => $config['href'] ?? null,
            // Colore del pallino in alto a destra - variarlo per card (vedi
            // ShowHomeDashboardTool) rende leggibile a colpo d'occhio una
            // griglia di piu' card, invece che un'unica tinta ripetuta;
            // 'petrol' (il colore di sempre) resta il default per chi non
            // lo passa, nessun cambiamento per gli usi gia' esistenti.
            'dotColor' => $config['dotColor'] ?? 'petrol',
        ];
    }
}
