<?php

declare(strict_types=1);

namespace App\Assistant;

use App\Core\Container;
use App\Model\SeasonalEvent;
use App\Repository\SeasonalEventRepository;

/**
 * I periodi forti dell'anno e quando muoversi. Si parte da una lista
 * comune (modificabile da /assistente/stagioni): le date "mobili" sono
 * approssimate al giorno piu' probabile (Festa della mamma, Black Friday),
 * la Pasqua si calcola.
 */
final class SeasonalCalendar
{
    /** [nome, mese, giorno, giorni dalla Pasqua, anticipo, parole chiave, cosa proporre] */
    private const DEFAULTS = [
        ['Natale', 12, 25, null, 50, 'natale, regal, panettone, dolci, pasticceria, cesti, gioiel, abbigliament, negozio, ristorant, vino, cantina, enoteca, prodotti tipici, idee regalo', 'Un piano per le feste: idee regalo e promozioni, contenuti a tema, auguri, orari speciali.'],
        ['Capodanno', 12, 31, null, 40, 'capodanno, ristorant, locale, discoteca, eventi, hotel, cenone', 'La serata di Capodanno: menu o evento, prenotazioni, contenuti per riempire i posti.'],
        ['San Valentino', 2, 14, null, 30, 'valentino, ristorant, gioiel, fiori, fiorista, profum, intimo, cioccolat, hotel, spa, estetica', 'San Valentino: proposta per le coppie, menu o pacchetti regalo, campagna sui social.'],
        ['Pasqua', null, null, 0, 35, 'pasqua, pasticceria, cioccolat, colomba, uova, ristorant, agriturism, hotel', 'Pasqua e Pasquetta: prodotti, menu, gite, prenotazioni.'],
        ['Festa della mamma', 5, 10, null, 25, 'mamma, fiori, fiorista, gioiel, estetica, profum, ristorant, abbigliament', 'Festa della mamma: idee regalo e un contenuto emozionale.'],
        ['Estate', 6, 21, null, 45, 'estate, turism, hotel, lido, spiaggia, mare, gelat, bar, ristorant, agriturism, eventi, piscina', "La stagione estiva: orari, eventi, offerte, contenuti per chi arriva in vacanza."],
        ['Ferragosto', 8, 15, null, 30, 'ferragosto, ristorant, lido, hotel, eventi, turism', 'Ferragosto: evento o menu, prenotazioni anticipate.'],
        ['Vendemmia', 9, 15, null, 35, 'vendemmia, vino, cantina, enoteca, agriturism, enogastronom', 'La vendemmia: esperienze in cantina, racconto del vino nuovo, visite.'],
        ['Rientro a settembre', 9, 10, null, 30, 'scuola, palestra, corsi, formazione, cartoleria, abbigliament, fitness', 'Il rientro: iscrizioni, nuovi corsi, offerte di inizio stagione.'],
        ['Halloween', 10, 31, null, 21, 'halloween, bar, pub, locale, eventi, dolci, pasticceria, discoteca', 'Halloween: serata a tema, contenuti divertenti.'],
        ['Black Friday', 11, 28, null, 30, 'black friday, negozio, e-commerce, ecommerce, shop, abbigliament, elettronic, gioiel, profum', 'Black Friday: sconti, campagna sponsorizzata, sito pronto al traffico.'],
    ];

    public function __construct(private readonly Container $container)
    {
    }

    /** La lista, creata la prima volta con i periodi di partenza. @return SeasonalEvent[] */
    public function events(): array
    {
        $repo = $this->container->get(SeasonalEventRepository::class);
        $events = $repo->findAll([], 'id');
        if ($events === []) {
            foreach (self::DEFAULTS as [$name, $month, $day, $easter, $lead, $keywords, $idea]) {
                $repo->insert(['name' => $name, 'month' => $month, 'day' => $day, 'easter_offset' => $easter, 'lead_days' => $lead, 'keywords' => $keywords, 'idea' => $idea, 'is_enabled' => 1]);
            }
            $events = $repo->findAll([], 'id');
        }

        return $events;
    }

    /**
     * I periodi che stanno arrivando: oggi e' dentro l'anticipo.
     *
     * @return list<array{event: SeasonalEvent, date: string, daysTo: int}>
     */
    public function upcoming(string $today): array
    {
        $result = [];
        foreach ($this->events() as $event) {
            if ((int) $event->isEnabled !== 1) {
                continue;
            }
            $date = $this->nextDate($event, $today);
            if ($date === null) {
                continue;
            }
            $daysTo = (int) round((strtotime($date) - strtotime($today)) / 86400);
            if ($daysTo >= 0 && $daysTo <= (int) $event->leadDays) {
                $result[] = ['event' => $event, 'date' => $date, 'daysTo' => $daysTo];
            }
        }
        usort($result, static fn (array $a, array $b) => $a['daysTo'] <=> $b['daysTo']);

        return $result;
    }

    public function nextDate(SeasonalEvent $event, string $today): ?string
    {
        $year = (int) substr($today, 0, 4);
        foreach ([$year, $year + 1] as $y) {
            $date = $this->dateIn($event, $y);
            if ($date !== null && $date >= $today) {
                return $date;
            }
        }

        return null;
    }

    /** Il cliente ha a che fare con questo periodo: una parola chiave (o il nome) nel testo che lo descrive. */
    public static function matches(SeasonalEvent $event, string $text): bool
    {
        $text = mb_strtolower($text);
        if (trim($text) === '') {
            return false;
        }
        $words = array_filter(array_map('trim', explode(',', mb_strtolower((string) $event->keywords . ',' . $event->name))));
        foreach ($words as $word) {
            if ($word !== '' && str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    private function dateIn(SeasonalEvent $event, int $year): ?string
    {
        if ($event->easterOffset !== null) {
            return date('Y-m-d', strtotime(self::easter($year) . ' ' . ((int) $event->easterOffset >= 0 ? '+' : '') . (int) $event->easterOffset . ' days'));
        }
        if ($event->month === null || $event->day === null || !checkdate((int) $event->month, (int) $event->day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, (int) $event->month, (int) $event->day);
    }

    /** La domenica di Pasqua (calendario gregoriano, algoritmo di Gauss/Meeus). */
    public static function easter(int $year): string
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
