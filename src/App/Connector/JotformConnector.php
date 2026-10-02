<?php

declare(strict_types=1);

namespace App\Connector;

final class JotformConnector extends AbstractApiKeyConnector
{
    public function code(): string
    {
        return 'jotform';
    }

    public function label(): string
    {
        return 'Jotform';
    }

    public function test(): bool
    {
        $key = $this->apiKey();
        if ($key === null) {
            return false;
        }

        try {
            $this->fetchForms($key, (string) ($this->meta()['region'] ?? 'us'));

            return true;
        } catch (ConnectorException) {
            return false;
        }
    }

    public function setupSpec(): array
    {
        return [
            'intro' => 'Collega Jotform per far arrivare qui i contatti raccolti dai tuoi moduli.',
            'steps' => [
                'Accedi a Jotform e apri Impostazioni, poi la voce "Chiavi API" (jotform.com/myaccount/api).',
                'Premi "Crea nuova chiave" e dai un nome riconoscibile, per esempio "Assilevi". Basta l\'accesso in sola lettura.',
                'Copia la chiave e incollala qui sotto. Se il tuo account Jotform e\' europeo (l\'indirizzo inizia con eu.jotform.com) scegli "Europa".',
            ],
            'fields' => [
                ['key' => 'api_key', 'label' => 'Chiave API', 'type' => 'password', 'placeholder' => 'Incolla qui la chiave'],
                [
                    'key' => 'region',
                    'label' => 'Server del tuo account',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'us', 'label' => 'Standard (jotform.com)'],
                        ['value' => 'eu', 'label' => 'Europa (eu.jotform.com)'],
                    ],
                ],
            ],
            'note' => 'Qui si verifica solo che la chiave funzioni e si vedono i moduli trovati. L\'importazione automatica dei contatti e\' il passo successivo.',
        ];
    }

    public function connect(array $input): string
    {
        $key = trim($input['api_key'] ?? '');
        $region = ($input['region'] ?? 'us') === 'eu' ? 'eu' : 'us';
        if ($key === '') {
            throw new ConnectorException('Incolla la chiave API di Jotform.');
        }

        $forms = $this->fetchForms($key, $region);
        $count = count($forms);
        $summary = $count === 1 ? '1 modulo trovato' : "{$count} moduli trovati";

        $this->saveVerified('api_key', ['api_key' => $key], ['region' => $region], $summary);

        return $summary;
    }

    /** @return list<array<string, mixed>> */
    private function fetchForms(string $key, string $region): array
    {
        $host = $region === 'eu' ? 'eu-api.jotform.com' : 'api.jotform.com';
        $response = Http::request('GET', "https://{$host}/user/forms?limit=100", ["APIKEY: {$key}"]);

        if (in_array($response['status'], [401, 403], true)) {
            throw new ConnectorException('Jotform non riconosce questa chiave. Controlla di averla copiata per intero e che il server scelto sia quello del tuo account.');
        }
        if ($response['status'] !== 200 || !is_array($response['json']['content'] ?? null)) {
            throw new ConnectorException("Jotform ha risposto in modo inatteso (codice {$response['status']}). Riprova tra poco.");
        }

        return $response['json']['content'];
    }
}
