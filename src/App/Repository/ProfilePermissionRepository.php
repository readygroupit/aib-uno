<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\ProfilePermission;

final class ProfilePermissionRepository extends AbstractRepository
{
    protected string $table = 'profile_permissions';
    protected string $modelClass = ProfilePermission::class;

    /** @return int[] i permessi del profilo */
    public function permissionIdsForProfile(int $profileId): array
    {
        $rows = $this->db->fetchAll('SELECT permission_id FROM profile_permissions WHERE status != 0 AND profile_id = :p', ['p' => $profileId]);

        return array_map('intval', array_column($rows, 'permission_id'));
    }

    /**
     * I permessi del profilo diventano esattamente questi: quelli tolti si
     * spengono (status 0, la storia resta), quelli nuovi si aggiungono.
     *
     * @param int[] $permissionIds
     */
    public function replaceForProfile(int $profileId, array $permissionIds, ?int $userId): void
    {
        $current = $this->permissionIdsForProfile($profileId);
        $wanted = array_values(array_unique(array_map('intval', $permissionIds)));
        $now = date('Y-m-d H:i:s');
        foreach (array_diff($current, $wanted) as $permissionId) {
            $this->db->execute(
                'UPDATE profile_permissions SET status = 0, updated_at = :now, updated_by = :u WHERE profile_id = :p AND permission_id = :perm AND status != 0',
                ['now' => $now, 'u' => $userId, 'p' => $profileId, 'perm' => $permissionId]
            );
        }
        foreach (array_diff($wanted, $current) as $permissionId) {
            $this->db->insert('profile_permissions', ['profile_id' => $profileId, 'permission_id' => $permissionId, 'status' => 1, 'created_at' => $now, 'created_by' => $userId]);
        }
    }
}
