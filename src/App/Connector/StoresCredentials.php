<?php

declare(strict_types=1);

namespace App\Connector;

use App\Model\ConnectorCredential;

/**
 * Persistenza comune a tutti i connettori: una riga in
 * connector_credentials per connettore, con l'esito dell'ultima verifica
 * dentro extra_config (JSON, chiavi 'verified_at' e 'summary' riservate
 * qui - il resto e' del connettore concreto). Usato da entrambe le basi
 * (ApiKey/OAuth) invece di duplicare la logica.
 *
 * Richiede che la classe che lo usa abbia $this->credentials e code().
 */
trait StoresCredentials
{
    /**
     * @param array<string, mixed> $columns colonne di connector_credentials da scrivere
     * @param array<string, mixed> $extra chiavi aggiuntive per extra_config
     */
    protected function saveVerified(string $authType, array $columns, array $extra, string $summary): void
    {
        $extra['verified_at'] = date('c');
        $extra['summary'] = $summary;

        $data = $columns + [
            'connector_code' => $this->code(),
            'auth_type' => $authType,
            'extra_config' => json_encode($extra, JSON_UNESCAPED_UNICODE),
        ];

        $existing = $this->credentials->findByConnectorCode($this->code());
        if ($existing === null) {
            $this->credentials->insert($data + ['api_key' => null, 'access_token' => null, 'refresh_token' => null, 'token_expires_at' => null]);

            return;
        }

        $this->credentials->update($existing->id, $data + ['api_key' => null, 'access_token' => null, 'refresh_token' => null, 'token_expires_at' => null]);
    }

    /** @return array<string, mixed> */
    protected function meta(): array
    {
        $credential = $this->credentials->findByConnectorCode($this->code());
        $decoded = $credential?->extraConfig !== null ? json_decode($credential->extraConfig, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    protected function credential(): ?ConnectorCredential
    {
        return $this->credentials->findByConnectorCode($this->code());
    }

    public function isConnected(): bool
    {
        return isset($this->meta()['verified_at']);
    }

    public function summary(): ?string
    {
        $summary = $this->meta()['summary'] ?? null;

        return is_string($summary) ? $summary : null;
    }

    public function disconnect(): void
    {
        $existing = $this->credentials->findByConnectorCode($this->code());
        if ($existing === null) {
            return;
        }

        // Eliminazione logica (vedi AbstractRepository::delete()) non basta:
        // le chiavi vanno azzerate davvero, non restare in chiaro su una
        // riga solo "nascosta".
        $this->credentials->update($existing->id, [
            'api_key' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'extra_config' => null,
            'status' => 0,
        ]);
    }
}
