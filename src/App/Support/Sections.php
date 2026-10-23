<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Tassonomia FISSA a 5 categorie, condivisa dal menu (ShowMenuTool) e
 * dal catalogo package (ListPackagesTool) - un pacchetto e la sua voce
 * di menu parlano dello stesso dominio applicativo, non ha senso avere
 * due vocabolari diversi per la stessa cosa. Un'unica fonte qui invece
 * di due copie che a un certo punto divergono (esattamente il tipo di
 * bug gia' capitato con le label del wizard in questo progetto). Colori
 * = token gia' esistenti in tokens.css, nessuno nuovo introdotto solo
 * per questo. Cresce solo per una categoria intera nuova (raro,
 * deliberato), non per ogni pacchetto/voce di menu nuova.
 */
final class Sections
{
    // L'ordine e' quello del menu: prima il lavoro di tutti i giorni, in
    // fondo le impostazioni. Le chiavi restano quelle storiche (manifest e
    // strumenti le usano), cambiano solo le etichette.
    public const ALL = [
        'operativita' => ['label' => 'Lavoro', 'color' => 'lilac'],
        'contenuti' => ['label' => 'Contenuti', 'color' => 'teal'],
        'crm' => ['label' => 'Clienti', 'color' => 'moss'],
        'vendite' => ['label' => 'Amministrazione', 'color' => 'brass'],
        'sistema' => ['label' => 'Impostazioni', 'color' => 'petrol'],
    ];

    public static function label(?string $key): ?string
    {
        return self::ALL[$key]['label'] ?? null;
    }

    public static function color(?string $key): string
    {
        return self::ALL[$key]['color'] ?? 'petrol';
    }
}
