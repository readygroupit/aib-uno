<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\DocumentRequest;

final class DocumentRequestRepository extends AbstractRepository
{
    protected string $table = 'document_requests';
    protected string $modelClass = DocumentRequest::class;
}
