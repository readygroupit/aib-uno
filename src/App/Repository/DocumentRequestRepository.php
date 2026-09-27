<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\DocumentRequest;

final class DocumentRequestRepository extends AbstractRepository
{
    protected string $table = 'document_requests';
    protected string $modelClass = DocumentRequest::class;

    /**
     * Documenti mancanti con il titolo della pratica collegata - la coda
     * "da approvare" del cruscotto home (vedi ShowHomeDashboardTool) deve
     * mostrare la pratica, non solo l'id numerico del documento.
     *
     * @return list<array{id:int,documentType:string,caseId:int,caseTitle:string}>
     */
    public function findMissingWithCase(int $limit = 5): array
    {
        $sql = 'SELECT d.id, d.document_type, d.case_id, c.title AS case_title
                FROM document_requests d
                JOIN cases c ON c.id = d.case_id
                WHERE d.status != 0 AND d.completeness_status = \'mancante\'
                ORDER BY d.created_at ASC
                LIMIT ' . max(1, $limit);

        return array_map(
            static fn (array $row) => [
                'id' => (int) $row['id'],
                'documentType' => (string) $row['document_type'],
                'caseId' => (int) $row['case_id'],
                'caseTitle' => (string) $row['case_title'],
            ],
            $this->db->fetchAll($sql)
        );
    }
}
