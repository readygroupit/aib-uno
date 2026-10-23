<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Calendario della settimana (o elenco dei prossimi giorni): appuntamenti per ora, viaggi, conflitti, attivita' del giorno, stato di Google Calendar. Dati da App\Calendar\CalendarOverviewService.
 */
final class CalendarWeekComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'calendar-week'] + $config;
    }
}
