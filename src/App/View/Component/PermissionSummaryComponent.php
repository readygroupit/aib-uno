<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * "Cosa puoi fare" - riepilogo di sola lettura dei permessi effettivi
 * dell'utente, raggruppati per dominio - vedi ShowProfileTool. Stessa
 * tassonomia/colori di ListPermissionsTool (view/create/edit/delete),
 * non un nuovo vocabolario per la stessa cosa.
 */
final class PermissionSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'permission-summary',
            'title' => $config['title'] ?? '',
            'subtitle' => $config['subtitle'] ?? '',
            // [{domain, permissions: [{name, category}]}]
            'groups' => $config['groups'] ?? [],
        ];
    }
}
