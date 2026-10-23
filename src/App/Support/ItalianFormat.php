<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Numeri e date come si dicono a voce: "1.250 €", "12 nov", "tra 3
 * giorni", "scaduto da 5 giorni". Un solo posto, cosi' ogni schermata e
 * ogni risposta del prompt parlano allo stesso modo.
 */
final class ItalianFormat
{
    private const MONTHS_SHORT = ['', 'gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'];
    private const MONTHS_LONG = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    /** Senza decimali quando sono zero: "1.250 €", altrimenti "1.250,50 €". */
    public static function euro(float $amount): string
    {
        $decimals = abs($amount - round($amount)) < 0.005 ? 0 : 2;

        return number_format($amount, $decimals, ',', '.') . ' €';
    }

    /** "12 nov", con l'anno solo se diverso da quello di $today. */
    public static function shortDate(?string $date, ?string $today = null): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $time = strtotime($date);
        $label = (int) date('j', $time) . ' ' . self::MONTHS_SHORT[(int) date('n', $time)];
        if (date('Y', $time) !== date('Y', strtotime($today ?? date('Y-m-d')))) {
            $label .= ' ' . date('Y', $time);
        }

        return $label;
    }

    public static function longDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $time = strtotime($date);

        return (int) date('j', $time) . ' ' . self::MONTHS_LONG[(int) date('n', $time)] . ' ' . date('Y', $time);
    }

    public static function monthName(int $month): string
    {
        return self::MONTHS_LONG[$month] ?? '';
    }

    /** Giorni da oggi (negativo = nel passato). */
    public static function daysFrom(string $date, string $today): int
    {
        return (int) round((strtotime($date) - strtotime($today)) / 86400);
    }

    /** "oggi", "domani", "tra 5 giorni", "ieri", "5 giorni fa". */
    public static function relativeDays(string $date, string $today): string
    {
        $days = self::daysFrom($date, $today);

        return match (true) {
            $days === 0 => 'oggi',
            $days === 1 => 'domani',
            $days === -1 => 'ieri',
            $days > 1 => "tra {$days} giorni",
            default => abs($days) . ' giorni fa',
        };
    }

    /** "1 servizio" / "3 servizi". */
    public static function plural(int $count, string $singular, string $plural): string
    {
        return $count . ' ' . ($count === 1 ? $singular : $plural);
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name))) ?: [];
        $words = array_values(array_filter($words, static fn (string $w) => mb_strlen($w) > 1 || ctype_upper($w)));
        if ($words === []) {
            return '?';
        }
        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }

        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    }
}
