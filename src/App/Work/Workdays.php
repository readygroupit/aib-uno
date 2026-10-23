<?php

declare(strict_types=1);

namespace App\Work;

/**
 * Le date pianificate dal sistema (fasi delle commesse, slittamenti,
 * attivita' che si ripetono ogni giorno) cadono nei giorni lavorativi: un
 * sabato o una domenica diventano il lunedi' dopo.
 */
final class Workdays
{
    public static function adjust(string $date): string
    {
        $weekday = (int) date('N', strtotime($date));

        return $weekday >= 6 ? date('Y-m-d', strtotime($date . ' +' . (8 - $weekday) . ' days')) : $date;
    }
}
