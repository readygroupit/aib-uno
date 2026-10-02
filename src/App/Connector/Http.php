<?php

declare(strict_types=1);

namespace App\Connector;

/**
 * Unico punto in cui i connettori parlano con la rete - curl (estensione
 * gia' presente in PHP), niente libreria esterna (regola "zero Composer").
 */
final class Http
{
    /**
     * @param string[] $headers righe complete "Nome: valore"
     * @return array{status: int, json: ?array}
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 12): array
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
        ]);

        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $failed = $raw === false;
        curl_close($handle);

        if ($failed) {
            $host = parse_url($url, PHP_URL_HOST);
            throw new ConnectorException("Impossibile raggiungere {$host}: controlla la connessione del server e riprova.");
        }

        $decoded = json_decode((string) $raw, true);

        return ['status' => $status, 'json' => is_array($decoded) ? $decoded : null];
    }
}
