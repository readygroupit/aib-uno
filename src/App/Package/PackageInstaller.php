<?php

declare(strict_types=1);

namespace App\Package;

/**
 * Traduce un manifest + una selezione di campi (decisa per il singolo
 * progetto al momento dell'installazione) in due cose:
 *  - 'core': la CREATE TABLE con solo le colonne scelte, ognuna NOT NULL
 *    o NULL secondo quanto scelto per quel cliente (non secondo un
 *    default fisso del pacchetto);
 *  - 'pending': una ALTER TABLE ... ADD COLUMN per ogni campo del
 *    catalogo non scelto ora. Non vengono eseguite: restano nel progetto
 *    generato come file pronti, cosi' se in futuro serve quel campo non
 *    si torna su uno per rigenerare nulla, si esegue solo il file gia'
 *    presente (sempre orchestrato da uno, mai a mano sul progetto).
 *
 * Formato di $selection: ['<entity>' => ['<fieldKey>' => 'required'|'optional']].
 * Un campo assente dalla mappa per quella entity = escluso (nessuna
 * colonna creata ora, finisce tra i pending). I campi con 'base' => true
 * nel manifest sono sempre inclusi come 'required', indipendentemente
 * dalla selezione — per un pacchetto come 'geo' dove ogni campo e' 'base'
 * $selection puo' essere []: non tutti i pacchetti hanno campi negoziabili,
 * la negoziazione e' un'opzione per-campo, non un obbligo del sistema.
 *
 * Un campo puo' avere 'references' => ['table' => ..., 'column' => 'id']
 * per generare una FOREIGN KEY: viene emessa solo se il campo e' incluso
 * ora (mai su un campo finito tra i pending, la colonna non esiste
 * ancora). L'ordine delle entity nel manifest e' anche l'ordine delle
 * CREATE TABLE emesse: un'entity che referenzia un'altra tabella dello
 * stesso pacchetto va dichiarata dopo di essa. Se la tabella referenziata
 * appartiene a un ALTRO pacchetto, aggiungere anche 'package' => '<nome>'
 * dentro 'references': serve a PackageInstallerRunner::resolveOrder()
 * per capire quale pacchetto installare prima, e solo se questo campo
 * e' stato davvero scelto (non e' un 'dependsOn' statico e incondizionato -
 * vedi il manifest di 'customers'). Non serve per una tabella del
 * framework sempre presente (es. 'users', 'attachments' - quelle non
 * sono pacchetti, esistono gia' in ogni progetto).
 *
 * Un campo 'base' => true e' sempre incluso ed e' NOT NULL di default;
 * 'nullable' => true lo lascia NULL pur restando sempre presente (es.
 * postal_codes.municipality_id: i CAP "speciali" - indirizzi esteri,
 * enti extraterritoriali - non hanno un comune italiano reale).
 */
final class PackageInstaller
{
    /** Colonne che ogni tabella riceve gia' da createTableStatement() - un campo di pacchetto non puo' chiamarsi cosi'. */
    private const RESERVED_FIELD_NAMES = ['id', 'status', 'created_at', 'created_by', 'updated_at', 'updated_by'];

    public function build(PackageManifest $manifest, array $selection): array
    {
        $core = '';
        $pending = [];

        foreach ($manifest->entities() as $entityKey => $entity) {
            $table = $entity['table'];
            $chosen = $selection[$entityKey] ?? [];

            $columns = [];
            $constraints = [];
            foreach ($entity['fields'] as $fieldKey => $field) {
                if (in_array($fieldKey, self::RESERVED_FIELD_NAMES, true)) {
                    throw new \RuntimeException(
                        "Il campo '{$fieldKey}' su '{$table}' collide con una colonna di sistema"
                    );
                }

                $isBase = $field['base'] ?? false;
                if ($isBase) {
                    $mode = ($field['nullable'] ?? false) ? 'optional' : 'required';
                } else {
                    $mode = $chosen[$fieldKey] ?? null;
                }

                if ($mode === null) {
                    $pending["{$table}__{$fieldKey}"] = $this->alterStatement($table, $fieldKey, $field);
                    continue;
                }

                $columns[] = $this->columnDefinition($fieldKey, $field, $mode === 'required');

                if (isset($field['references'])) {
                    $constraints[] = $this->foreignKeyConstraint($table, $fieldKey, $field['references']);
                }
            }

            $core .= $this->createTableStatement($table, $columns, $constraints);
        }

        return ['core' => $core, 'pending' => $pending];
    }

    private function columnDefinition(string $fieldKey, array $field, bool $required): string
    {
        $null = $required ? 'NOT NULL' : 'NULL';

        return "  `{$fieldKey}` {$field['sql']} {$null}";
    }

    private function foreignKeyConstraint(string $table, string $fieldKey, array $references): string
    {
        $refTable = $references['table'];
        $refColumn = $references['column'] ?? 'id';
        $name = "fk_{$table}_{$fieldKey}";

        return "  CONSTRAINT `{$name}` FOREIGN KEY (`{$fieldKey}`) REFERENCES `{$refTable}` (`{$refColumn}`)";
    }

    private function createTableStatement(string $table, array $columns, array $constraints = []): string
    {
        $lines = array_merge(
            [
                '  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY',
                '  `status` TINYINT NOT NULL DEFAULT 1',
            ],
            $columns,
            [
                '  `created_at` DATETIME NOT NULL',
                '  `created_by` INT UNSIGNED NULL',
                '  `updated_at` DATETIME NULL',
                '  `updated_by` INT UNSIGNED NULL',
            ],
            $constraints
        );

        return "CREATE TABLE `{$table}` (\n" . implode(",\n", $lines) . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
    }

    private function alterStatement(string $table, string $fieldKey, array $field): string
    {
        return "ALTER TABLE `{$table}` ADD COLUMN `{$fieldKey}` {$field['sql']} NULL;\n";
    }
}
