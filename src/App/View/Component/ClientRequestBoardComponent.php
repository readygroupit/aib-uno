<?php

declare(strict_types=1);

namespace App\View\Component;

/** Richieste dei clienti per cosa c'e' da fare: smistare, fare, mettere in conto. */
final class ClientRequestBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'request-board',
            'title' => $config['title'] ?? 'Richieste dei clienti',
            'url' => $config['url'] ?? '/richieste',
            'stats' => $config['stats'] ?? [],
            'groups' => $config['groups'] ?? [],
            'done' => $config['done'] ?? [],
            'top' => $config['top'] ?? [],
            'createHref' => $config['createHref'] ?? null,
        ];
    }
}
