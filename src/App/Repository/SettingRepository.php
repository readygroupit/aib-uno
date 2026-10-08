<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Setting;

final class SettingRepository extends AbstractRepository
{
    protected string $table = 'settings';
    protected string $modelClass = Setting::class;

    /** Uno e i progetti nati prima del primo accesso non hanno la tabella. */
    public function tableExists(): bool
    {
        return $this->db->fetchRow(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $this->table]
        ) !== null;
    }

    /** @return array<string, string|null> chiave => valore, per prefisso (es. 'project.') */
    public function valuesByPrefix(string $prefix): array
    {
        $values = [];
        foreach ($this->db->fetchAll("SELECT setting_key, value FROM {$this->table} WHERE status != 0 AND setting_key LIKE :prefix", ['prefix' => $prefix . '%']) as $row) {
            $values[$row['setting_key']] = $row['value'];
        }

        return $values;
    }

    public function value(string $key): ?string
    {
        $row = $this->db->fetchRow("SELECT value FROM {$this->table} WHERE status != 0 AND setting_key = :key", ['key' => $key]);

        return $row === null ? null : $row['value'];
    }

    public function set(string $key, ?string $value): void
    {
        $existing = $this->findAll(['setting_key' => $key], '', true);
        if ($existing === []) {
            $this->insert(['setting_key' => $key, 'value' => $value]);

            return;
        }
        $this->update((int) $existing[0]->id, ['value' => $value, 'status' => 1]);
    }
}
