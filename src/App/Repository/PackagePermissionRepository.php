<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Container;
use App\Service\DbService;

/**
 * Permessi di un pacchetto installato DOPO la creazione del progetto
 * (bin/install-packages.php): stessa convenzione di db/seed.sql, un gruppo
 * con il nome del pacchetto e i quattro permessi view/create/edit/delete,
 * dati al profilo amministratore. Se esistono gia' non tocca nulla.
 */
final class PackagePermissionRepository
{
    private const ACTIONS = [
        'view' => ['Visualizzare', "Vedere l'elenco e aprire il dettaglio."],
        'create' => ['Creare', 'Aggiungere nuovi elementi.'],
        'edit' => ['Modificare', 'Modificare elementi esistenti.'],
        'delete' => ['Eliminare', 'Eliminare elementi esistenti.'],
    ];

    private DbService $db;

    public function __construct(Container $container)
    {
        $this->db = $container->get(DbService::class);
    }

    /**
     * @param ?array<string, array{0:string, 1:string}> $custom permessi propri del pacchetto
     *     ('permissions' nel manifest: azione => [nome, descrizione]) al posto dei quattro soliti
     * @return int permessi creati
     */
    public function ensure(string $package, string $label, int $adminProfileId = 1, ?array $custom = null): int
    {
        $now = date('Y-m-d H:i:s');
        $group = $this->db->fetchRow('SELECT id FROM permission_groups WHERE code = :code AND status != 0', ['code' => $package]);
        if ($group === null) {
            $parent = $this->db->fetchRow("SELECT id FROM permission_groups WHERE code = 'administration' AND status != 0");
            $groupId = $this->db->insert('permission_groups', [
                'parent_id' => $parent['id'] ?? null,
                'code' => $package,
                'name' => $label,
                'description' => "Gestione {$this->lower($label)}",
                'sort_order' => 0,
                'status' => 1,
                'created_at' => $now,
            ]);
        } else {
            $groupId = (int) $group['id'];
        }

        $created = 0;
        $actions = $custom ?? array_map(fn (array $a) => [$a[0] . ' ' . $this->lower($label), $a[1]], self::ACTIONS);
        foreach ($actions as $action => [$name, $description]) {
            $code = "{$package}.{$action}";
            if ($this->db->fetchRow('SELECT id FROM permissions WHERE code = :code AND status != 0', ['code' => $code]) !== null) {
                continue;
            }
            $permissionId = $this->db->insert('permissions', [
                'permission_group_id' => $groupId,
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'category' => in_array($action, ['view', 'create', 'edit', 'delete'], true) ? $action : 'edit',
                'sort_order' => 0,
                'status' => 1,
                'created_at' => $now,
            ]);
            $this->db->insert('profile_permissions', [
                'profile_id' => $adminProfileId,
                'permission_id' => $permissionId,
                'status' => 1,
                'created_at' => $now,
            ]);
            $created++;
        }

        return $created;
    }

    private function lower(string $label): string
    {
        return mb_strtolower($label);
    }
}
