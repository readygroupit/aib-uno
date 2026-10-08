<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Link del primo accesso a un progetto generato: lo slug cifrato
 * (AES-256-CBC, chiave provisioning.tokenKey di global.php, la stessa su
 * Uno e sui progetti). Serve solo finche' il progetto non e' configurato:
 * dopo FirstAccessService non lo guarda piu'.
 */
final class FirstAccessToken
{
    private const CIPHER = 'aes-256-cbc';

    public static function forSlug(string $slug, string $key): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $cipher = (string) openssl_encrypt($slug, self::CIPHER, self::key($key), OPENSSL_RAW_DATA, $iv);

        return rtrim(strtr(base64_encode($iv . $cipher), '+/', '-_'), '=');
    }

    public static function slugFrom(string $token, string $key): ?string
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        $length = openssl_cipher_iv_length(self::CIPHER);
        if ($raw === false || strlen($raw) <= $length) {
            return null;
        }
        $slug = openssl_decrypt(substr($raw, $length), self::CIPHER, self::key($key), OPENSSL_RAW_DATA, substr($raw, 0, $length));

        return $slug === false ? null : $slug;
    }

    private static function key(string $key): string
    {
        return hash('sha256', $key, true);
    }
}
