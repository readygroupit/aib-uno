<?php

declare(strict_types=1);

namespace App\Analysis;

use App\Core\Container;
use App\Package\PackageCatalogService;
use App\Package\PackageManifest;
use App\Repository\AnalysisRepository;

/**
 * Cosa puo' interrogare l'analisi libera: le tabelle dei pacchetti
 * installati (etichette dai manifest) piu' gli utenti, con i soli campi
 * ammessi. Mai password, token, chiavi, credenziali dei connettori,
 * configurazioni o permessi. Per i campi "a categorie" aggiunge i valori
 * presenti, per i collegamenti la tabella collegata.
 */
final class DataCatalog
{
    /** Tabelle di sistema mai interrogabili. */
    private const EXCLUDED_TABLES = [
        'password_resets', 'connector_credentials', 'configs', 'config_groups', 'settings', 'permissions',
        'permission_groups', 'profile_permissions', 'user_permissions', 'scheduled_jobs', 'scheduled_job_runs',
        'attachments', 'communication_contents', 'agent_messages',
    ];
    /** Tabelle di riferimento: servono solo a dare un nome ai collegamenti (es. il comune). */
    private const REFERENCE_ONLY = ['regions', 'districts', 'municipalities', 'postal_codes', 'profiles'];
    /** Campi tecnici: ammessi nelle richieste ma non descritti all'IA (risparmio di token). */
    private const QUIET_FIELDS = ['id', 'updated_at', 'updated_by'];

    /** Campi mai esposti, in qualunque tabella. */
    private const SENSITIVE = '/pass|token|secret|api_?key|hash|extra_config|credential|iban|fiscal|tax_code/i';
    /** Campi di testo libero: niente valori di esempio, non servono a raggruppare. */
    private const FREE_TEXT = '/name|email|phone|address|note|description|body|text|title|website|url|href|code|key|subject|detail/i';

    private const USERS = [
        'label' => 'Utenti del gestionale (operatori)',
        'fields' => [
            'first_name' => 'Nome', 'last_name' => 'Cognome', 'username' => 'Nome utente',
            'profile_id' => 'Profilo', 'last_login_at' => 'Ultimo accesso', 'created_at' => 'Data creazione',
        ],
    ];

    private const COMMON_LABELS = [
        'created_at' => 'Data di creazione', 'updated_at' => 'Data ultima modifica',
        'created_by' => 'Creato da (utente)', 'updated_by' => 'Modificato da (utente)', 'id' => 'Id',
    ];

    private ?array $catalog = null;

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @return array<string, array{label: string, rows: int, labelSql: string, fields: array<string, array{label: string, type: string, ref?: string, values?: string[]}>}>
     */
    public function tables(): array
    {
        if ($this->catalog !== null) {
            return $this->catalog;
        }

        $repository = $this->container->get(AnalysisRepository::class);
        $definitions = ['users' => self::USERS];
        foreach (PackageCatalogService::installedNames() as $package) {
            try {
                $manifest = new PackageManifest($package);
            } catch (\Throwable) {
                continue;
            }
            foreach ($manifest->entities() as $entityKey => $entity) {
                $table = (string) ($entity['table'] ?? $entityKey);
                if (in_array($table, self::EXCLUDED_TABLES, true)) {
                    continue;
                }
                $fields = [];
                foreach ($entity['fields'] ?? [] as $name => $field) {
                    $fields[$name] = (string) ($field['label'] ?? $name);
                    if (isset($field['references']['table'])) {
                        $fields[$name . '@ref'] = (string) $field['references']['table'];
                    }
                }
                $definitions[$table] = ['label' => (string) ($entity['label'] ?? $manifest->label()), 'fields' => $fields];
            }
        }

        $columns = $repository->columns(array_keys($definitions));
        $catalog = [];
        foreach ($definitions as $table => $definition) {
            if (!isset($columns[$table])) {
                continue;
            }
            $fields = [];
            foreach ($columns[$table] as $name => $type) {
                if (preg_match(self::SENSITIVE, $name) || $name === 'status') {
                    continue;
                }
                $isUsers = $table === 'users';
                if ($isUsers && !isset($definition['fields'][$name]) && $name !== 'id') {
                    continue;
                }
                $field = ['label' => $definition['fields'][$name] ?? self::COMMON_LABELS[$name] ?? $name, 'type' => self::type($type)];
                if (isset($definition['fields'][$name . '@ref'])) {
                    $field['ref'] = $definition['fields'][$name . '@ref'];
                } elseif (in_array($name, ['created_by', 'updated_by', 'assigned_user_id'], true)) {
                    $field['ref'] = 'users';
                } elseif ($name === 'profile_id') {
                    $field['ref'] = 'profiles';
                } elseif (preg_match('/^(.+)_id$/', $name, $m) && isset($definitions[$m[1] . 's'])) {
                    $field['ref'] = $m[1] . 's';
                }
                $fields[$name] = $field;
            }

            $catalog[$table] = [
                'label' => $definition['label'],
                'rows' => 0,
                'labelSql' => self::labelSql(array_keys($fields)),
                'fields' => $fields,
                'hidden' => in_array($table, self::REFERENCE_ONLY, true),
            ];
        }

        foreach ($catalog as $table => &$entry) {
            if ($entry['hidden']) {
                continue;
            }
            $entry['rows'] = $repository->countRows($table);
            $candidates = [];
            foreach ($entry['fields'] as $name => $field) {
                if ($field['type'] === 'testo' && !isset($field['ref']) && !preg_match(self::FREE_TEXT, $name)) {
                    $candidates[] = $name;
                }
            }
            foreach ($repository->categoryValues($table, $candidates) as $name => $values) {
                $entry['fields'][$name]['values'] = $values;
            }
        }
        unset($entry);
        // Profili: solo per dare un nome a users.profile_id.
        $catalog['profiles'] ??= ['label' => 'Profili utente', 'rows' => 0, 'labelSql' => '{a}.`name`', 'fields' => ['name' => ['label' => 'Nome', 'type' => 'testo']], 'hidden' => true];

        return $this->catalog = $catalog;
    }

    /** Testo compatto per l'IA: una riga per campo. */
    public function describe(): string
    {
        $lines = [];
        foreach ($this->tables() as $table => $entry) {
            if (!empty($entry['hidden'])) {
                continue;
            }
            $lines[] = "## {$table} - {$entry['label']} ({$entry['rows']} righe)";
            foreach ($entry['fields'] as $name => $field) {
                if (in_array($name, self::QUIET_FIELDS, true)) {
                    continue;
                }
                $line = "- {$name}: {$field['label']} [{$field['type']}]";
                if (isset($field['ref'])) {
                    $line .= " (collegato a {$field['ref']}: raggruppato o selezionato restituisce il nome)";
                }
                if (!empty($field['values'])) {
                    $line .= ' valori: ' . implode(' | ', $field['values']);
                }
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    public function has(string $table): bool
    {
        $tables = $this->tables();

        return isset($tables[$table]) && empty($tables[$table]['hidden']);
    }

    private static function type(string $sqlType): string
    {
        $base = explode(':', $sqlType)[0];

        return match (true) {
            in_array($base, ['date', 'datetime', 'timestamp'], true) => 'data',
            in_array($base, ['int', 'bigint', 'smallint', 'mediumint'], true) => 'intero',
            in_array($base, ['tinyint'], true) => 'si/no o piccolo numero',
            in_array($base, ['decimal', 'float', 'double'], true) => 'numero',
            default => 'testo',
        };
    }

    /** Come mostrare un elemento collegato (es. un utente: nome e cognome). */
    private static function labelSql(array $fields): string
    {
        if (in_array('first_name', $fields, true) && in_array('last_name', $fields, true)) {
            return "NULLIF(TRIM(CONCAT_WS(' ', {a}.`first_name`, {a}.`last_name`)), '')";
        }
        foreach (['name', 'title', 'code', 'company_name'] as $candidate) {
            if (in_array($candidate, $fields, true)) {
                return "{a}.`{$candidate}`";
            }
        }

        return "CONCAT('#', {a}.`id`)";
    }
}
