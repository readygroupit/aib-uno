<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Protezione CSRF a token di sessione (uno per sessione, non uno per
 * form): generato alla prima richiesta e verificato ad ogni POST da
 * Application::run(), prima di eseguire qualunque action - un solo punto
 * di controllo per tutto il framework, cosi' non va ricordato form per
 * form. Vive in $_SESSION (sessione avviata in public/index.php),
 * coerente con le pagine costruite lato client che tengono piu' risultati
 * in memoria senza mai un reload completo (vedi hero.js): un token legato
 * alla sessione, non alla singola pagina, resta valido per tutta la sua
 * durata.
 */
final class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $submitted): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? null;

        return $expected !== null && $submitted !== null && hash_equals($expected, $submitted);
    }
}
