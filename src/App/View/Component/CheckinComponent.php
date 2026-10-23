<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * "Cosa hai fatto?": le righe proposte da confermare (fatto, in parte, non fatto) con la durata da correggere, piu' le cose fatte fuori programma. Dati da App\Worklog\CheckinService.
 */
final class CheckinComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'check-in'] + $config;
    }
}
