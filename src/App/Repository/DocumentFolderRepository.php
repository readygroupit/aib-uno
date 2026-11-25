<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\DocumentFolder;

final class DocumentFolderRepository extends AbstractRepository
{
    protected string $table = 'document_folders';
    protected string $modelClass = DocumentFolder::class;

    public function findByExternalId(string $externalId): ?DocumentFolder
    {
        $rows = $this->findAll(['external_id' => $externalId], 'id');

        return $rows[0] ?? null;
    }

    /** @return DocumentFolder[] le cartelle dentro (null = quelle in cima), non nel cestino */
    public function children(?int $parentId): array
    {
        $rows = $parentId === null
            ? $this->db->fetchAll('SELECT * FROM document_folders WHERE status != 0 AND parent_id IS NULL AND trashed_at IS NULL ORDER BY name')
            : $this->db->fetchAll('SELECT * FROM document_folders WHERE status != 0 AND parent_id = :p AND trashed_at IS NULL ORDER BY name', ['p' => $parentId]);

        return array_map(static fn (array $row) => DocumentFolder::fromArray($row), $rows);
    }

    public function findForCustomerRoot(int $customerId): ?DocumentFolder
    {
        $row = $this->db->fetchRow(
            'SELECT * FROM document_folders WHERE status != 0 AND trashed_at IS NULL AND customer_id = :c AND work_order_id IS NULL ORDER BY parent_id IS NOT NULL, id LIMIT 1',
            ['c' => $customerId]
        );

        return $row !== null ? DocumentFolder::fromArray($row) : null;
    }

    public function findForWorkOrder(int $workOrderId): ?DocumentFolder
    {
        $row = $this->db->fetchRow(
            'SELECT f.* FROM document_folders f LEFT JOIN document_folders p ON p.id = f.parent_id
             WHERE f.status != 0 AND f.trashed_at IS NULL AND f.work_order_id = :w AND (p.id IS NULL OR p.work_order_id IS NULL OR p.work_order_id != :w2)
             ORDER BY f.id LIMIT 1',
            ['w' => $workOrderId, 'w2' => $workOrderId]
        );

        return $row !== null ? DocumentFolder::fromArray($row) : null;
    }

    /** @return int[] tutte le cartelle sotto (compresa questa) */
    public function descendantIds(int $folderId): array
    {
        $ids = [$folderId];
        $frontier = [$folderId];
        while ($frontier !== []) {
            $placeholders = implode(',', array_fill(0, count($frontier), '?'));
            $rows = $this->db->fetchAll("SELECT id FROM document_folders WHERE status != 0 AND parent_id IN ({$placeholders})", $frontier);
            $frontier = array_values(array_diff(array_map(static fn (array $r) => (int) $r['id'], $rows), $ids));
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    /** @param int[] $ids */
    public function markTrashed(array $ids, ?string $at): void
    {
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->execute("UPDATE document_folders SET trashed_at = ? WHERE id IN ({$placeholders})", [$at, ...$ids]);
    }

    /** @return array<string,int> id sul Drive => id, delle cartelle non nel cestino */
    public function liveExternalIds(): array
    {
        $rows = $this->db->fetchAll('SELECT id, external_id FROM document_folders WHERE status != 0 AND trashed_at IS NULL');

        return array_map('intval', array_column($rows, 'id', 'external_id'));
    }
}
