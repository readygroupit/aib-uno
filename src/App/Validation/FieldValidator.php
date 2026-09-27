<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Validatori per formati di campo ricorrenti (numerico, decimale con
 * precisione, codice fiscale, partita IVA, IBAN, data/orario) - presi a
 * spunto dal StringHelper di Core (vedi /var/www/core), non copiati:
 * la' vivono anche formattazione/mascheramento lato JS, qui per ora solo
 * la validazione server-side, pura e senza dipendenze (nessun Container,
 * nessuna query), cosi' e' chiamabile sia da un Controller sia da un
 * Prompt Tool senza doverla risolvere dal DI.
 *
 * validate() ritorna null se il valore rispetta il formato, altrimenti
 * un messaggio d'errore in italiano gia' pronto da mostrare - stesso
 * approccio delle validazioni gia' scritte a mano in UsersController,
 * cosi' un domani si puo' sostituire quel codice ad-hoc con una chiamata
 * qui senza cambiare la forma del risultato.
 *
 * Il campo puo' essere vuoto ('' o null): la validazione di formato non
 * si occupa di "obbligatorio", quella resta una scelta separata di chi
 * chiama (vedi FORMAT_* + $required nei field def di packages/*.php).
 */
final class FieldValidator
{
    public const FORMAT_INTEGER = 'integer';
    public const FORMAT_DECIMAL = 'decimal';
    public const FORMAT_EMAIL = 'email';
    public const FORMAT_PHONE = 'phone';
    public const FORMAT_DATE = 'date';
    public const FORMAT_DATETIME = 'datetime';
    public const FORMAT_CODICE_FISCALE = 'codice_fiscale';
    public const FORMAT_PARTITA_IVA = 'partita_iva';
    public const FORMAT_IBAN = 'iban';

    /**
     * @param array{precision?: int} $options 'precision' solo per FORMAT_DECIMAL (default 2)
     */
    public static function validate(string $format, mixed $value, array $options = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        return match ($format) {
            self::FORMAT_INTEGER => self::checkInteger($value),
            self::FORMAT_DECIMAL => self::checkDecimal($value, $options['precision'] ?? 2),
            self::FORMAT_EMAIL => self::checkEmail($value),
            self::FORMAT_PHONE => self::checkPhone($value),
            self::FORMAT_DATE => self::checkDate($value, 'Y-m-d'),
            self::FORMAT_DATETIME => self::checkDate($value, 'Y-m-d H:i:s'),
            self::FORMAT_CODICE_FISCALE => self::checkCodiceFiscale($value),
            self::FORMAT_PARTITA_IVA => self::checkPartitaIva($value),
            self::FORMAT_IBAN => self::checkIban($value),
            default => null,
        };
    }

    private static function checkInteger(string $value): ?string
    {
        return preg_match('/^-?[0-9]+$/', $value) === 1 ? null : 'Deve essere un numero intero.';
    }

    private static function checkDecimal(string $value, int $precision): ?string
    {
        $pattern = '/^-?[0-9]+(\.[0-9]{1,' . $precision . '})?$/';

        if (preg_match($pattern, $value) !== 1) {
            return "Deve essere un numero con al massimo {$precision} cifre decimali.";
        }

        return null;
    }

    private static function checkEmail(string $value): ?string
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : 'Indirizzo email non valido.';
    }

    /**
     * Non solo numeri italiani (un contatto puo' essere estero): accetta
     * '+', spazi, punti, trattini, parentesi come separatori, poi conta
     * solo le cifre vere - tra 6 e 15 (E.164, il massimo internazionale),
     * cosi' non serve una regex diversa per fisso/mobile/prefisso.
     */
    private static function checkPhone(string $value): ?string
    {
        $trimmed = trim($value);

        if (preg_match('/^[+0-9(][0-9 .\-()]*$/', $trimmed) !== 1) {
            return 'Numero di telefono non valido.';
        }

        $digits = preg_replace('/\D/', '', $trimmed);

        return (strlen($digits) >= 6 && strlen($digits) <= 15) ? null : 'Numero di telefono non valido.';
    }

    private static function checkDate(string $value, string $format): ?string
    {
        $date = \DateTime::createFromFormat($format, $value);
        $errors = \DateTime::getLastErrors();

        // Da PHP 8.2, getLastErrors() ritorna false (non piu' un array
        // con contatori a zero) quando il parsing non ha prodotto ne'
        // errori ne' warning - va trattato come "nessun problema", non
        // come fallimento (il contrario di prima rifiutava ogni data
        // valida).
        $clean = $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
        $valid = $date !== false && $clean;

        return $valid ? null : ('Data non valida (formato atteso: ' . $format . ').');
    }

    /**
     * Algoritmo ufficiale del codice fiscale italiano (16 caratteri):
     * ogni carattere in posizione dispari/pari (1-indicizzata) ha un
     * valore associato da due tabelle diverse, la somma modulo 26 da'
     * l'indice della lettera di controllo (17-esimo carattere).
     */
    private static function checkCodiceFiscale(string $value): ?string
    {
        $value = strtoupper(trim($value));

        if (!preg_match('/^[A-Z0-9]{16}$/', $value)) {
            return 'Codice fiscale non valido: deve avere 16 caratteri alfanumerici.';
        }

        $odd = [
            '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
            'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
            'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
            'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
        ];
        $even = [
            '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
            'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
            'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
        ];
        $checkLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $char = $value[$i];
            $sum += ($i % 2 === 0) ? $odd[$char] : $even[$char];
        }

        $expected = $checkLetters[$sum % 26];

        return $value[15] === $expected ? null : 'Codice fiscale non valido: carattere di controllo errato.';
    }

    /**
     * Partita IVA italiana (11 cifre): le prime 7 sono il numero
     * progressivo, le successive 3 il codice ufficio, l'ultima e' quella
     * di controllo calcolata sulla somma delle prime 10 (cifre in
     * posizione pari raddoppiate, sottraendo 9 se il risultato supera 9).
     */
    private static function checkPartitaIva(string $value): ?string
    {
        $value = trim($value);

        if (!preg_match('/^[0-9]{11}$/', $value)) {
            return 'Partita IVA non valida: deve avere 11 cifre.';
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $digit = (int) $value[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return $checkDigit === (int) $value[10] ? null : 'Partita IVA non valida: cifra di controllo errata.';
    }

    /**
     * ISO 7064 mod-97-10: si spostano i primi 4 caratteri in fondo, si
     * convertono le lettere in numeri (A=10..Z=35), e il resto della
     * divisione per 97 del numero risultante deve essere 1.
     */
    private static function checkIban(string $value): ?string
    {
        $value = strtoupper(str_replace(' ', '', $value));

        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $value)) {
            return 'IBAN non valido: formato non riconosciuto.';
        }

        $rearranged = substr($value, 4) . substr($value, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = '0';
        foreach (str_split($numeric) as $digit) {
            $remainder = (string) ((((int) $remainder) * 10 + (int) $digit) % 97);
        }

        return $remainder === '1' ? null : 'IBAN non valido: cifra di controllo errata.';
    }
}
