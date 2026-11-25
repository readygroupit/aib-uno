<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Document;

final class DocumentRepository extends AbstractRepository
{
    protected string $table = 'documents';
    protected string $modelClass = Document::class;

    public function findByExternalId(string $externalId): ?Document
    {
        $rows = $this->findAll(['external_id' => $externalId], 'id');

        return $rows[0] ?? null;
    }

    /** @return Document[] */
    public function inFolder(int $folderId): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM documents WHERE status != 0 AND folder_id = :f AND trashed_at IS NULL ORDER BY name', ['f' => $folderId]);

        return array_map(static fn (array $row) => Document::fromArray($row), $rows);
    }

    /** @param int[] $folderIds @return array<int,int> cartella => numero di file */
    public function countInFolders(array $folderIds): array
    {
        if ($folderIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        $rows = $this->db->fetchAll("SELECT folder_id, COUNT(*) n FROM documents WHERE status != 0 AND trashed_at IS NULL AND folder_id IN ({$placeholders}) GROUP BY folder_id", array_values($folderIds));

        return array_map('intval', array_column($rows, 'n', 'folder_id'));
    }

    /** @param int[] $folderIds */
    public function markTrashedInFolders(array $folderIds, ?string $at): void
    {
        if ($folderIds === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($folderIds), '?'));
        $this->db->execute("UPDATE documents SET trashed_at = ? WHERE folder_id IN ({$placeholders})", [$at, ...$folderIds]);
    }

    /** @return array<string,int> id sul Drive => id, dei file non nel cestino */
    public function liveExternalIds(): array
    {
        $rows = $this->db->fetchAll('SELECT id, external_id FROM documents WHERE status != 0 AND trashed_at IS NULL');

        return array_map('intval', array_column($rows, 'id', 'external_id'));
    }

    /**
     * Cerca nelle informazioni salvate (nome, descrizione, parole chiave,
     * cartella), parola per parola: tutte devono esserci.
     *
     * @return Document[]
     */
    public function search(string $query, ?int $customerId, int $limit): array
    {
        $words = array_slice(array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($query))) ?: [], static fn (string $w) => mb_strlen($w) >= 2)), 0, 6);
        $where = ['d.status != 0', 'd.trashed_at IS NULL'];
        $params = [];
        foreach ($words as $i => $word) {
            $where[] = "(LOWER(d.name) LIKE :w{$i}a OR LOWER(d.description) LIKE :w{$i}b OR LOWER(d.tags) LIKE :w{$i}c OR LOWER(f.name) LIKE :w{$i}d)";
            foreach (['a', 'b', 'c', 'd'] as $k) {
                $params["w{$i}{$k}"] = '%' . $word . '%';
            }
        }
        if ($customerId !== null) {
            $where[] = 'd.customer_id = :c';
            $params['c'] = $customerId;
        }
        $rows = $this->db->fetchAll(
            'SELECT d.* FROM documents d LEFT JOIN document_folders f ON f.id = d.folder_id WHERE ' . implode(' AND ', $where)
            . ' ORDER BY d.drive_modified_at DESC, d.id DESC LIMIT ' . max(1, $limit),
            $params
        );

        return array_map(static fn (array $row) => Document::fromArray($row), $rows);
    }

    /** I file caricati da una fase (anche quelli in attesa del Drive), i piu' recenti prima. @return Document[] */
    public function findForTask(int $taskId): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM documents WHERE status != 0 AND trashed_at IS NULL AND task_id = :t ORDER BY id DESC', ['t' => $taskId]);

        return array_map(static fn (array $row) => Document::fromArray($row), $rows);
    }

    /** I file caricati quando il Drive non c'era: da mandare. @return Document[] */
    public function findPendingLocal(): array
    {
        $rows = $this->db->fetchAll("SELECT * FROM documents WHERE status != 0 AND trashed_at IS NULL AND local_path IS NOT NULL AND external_id LIKE 'local:%' ORDER BY id");

        return array_map(static fn (array $row) => Document::fromArray($row), $rows);
    }

    /** I file di un progetto con le fasi da cui arrivano (per l'IA). @return Document[] */
    public function findForWorkOrder(int $workOrderId, int $limit = 40): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM documents WHERE status != 0 AND trashed_at IS NULL AND work_order_id = :w ORDER BY id DESC LIMIT ' . max(1, $limit), ['w' => $workOrderId]);

        return array_map(static fn (array $row) => Document::fromArray($row), $rows);
    }
}
