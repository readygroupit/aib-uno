<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Scheda di un appuntamento: quando, dove (con la mappa), chi, videochiamata, da portare, conflitti, stato su Google Calendar.
 */
final class AppointmentSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'appointment-summary'] + $config;
    }
}
