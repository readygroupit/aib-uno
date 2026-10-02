<?php

declare(strict_types=1);

namespace App\Provisioning;

/**
 * Esegue uno script SQL scritto a mano (schema.sql, seed.sql, dati demo)
 * istruzione per istruzione. Le righe di commento vanno tolte una per una
 * PRIMA di guardare cosa resta: un commento '-- ...' non ha un ';' suo e
 * resta incollato all'istruzione che lo segue (un controllo "il blocco
 * comincia per --" saltava interi INSERT - successo davvero).
 */
final class SqlScript
{
    public static function run(\PDO $pdo, string $sql): void
    {
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $chunk) {
            $lines = array_filter(
                explode("\n", $chunk),
                static fn ($line) => !str_starts_with(trim($line), '--') && !str_starts_with(trim($line), '/*')
            );
            $statement = rtrim(trim(implode("\n", $lines)), ';');

            if ($statement === '' || preg_match('/^SET\s/i', $statement) === 1) {
                continue;
            }
            $pdo->exec($statement);
        }
    }
}
