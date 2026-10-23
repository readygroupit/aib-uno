<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Un contenuto del piano editoriale come lo vedra' chi lo guarda (anteprima
 * tipo telefono), con stato, chi lo prepara, commento del cliente e il
 * passo successivo a un clic. Dati da EditorialOverviewService::detail().
 */
final class PostSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'post-summary', 'url' => $config['url'] ?? null, 'actions' => $config['actions'] ?? []] + ($config['post'] ?? []);
    }
}
