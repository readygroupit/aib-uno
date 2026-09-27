<?php

declare(strict_types=1);

namespace App\Model;

final class DocumentRequest extends AbstractModel
{
    public ?int $caseId = null;
    public ?string $documentType = null;
    public ?string $completenessStatus = null;
    public ?int $attachmentId = null;
    public ?string $requestedAt = null;
    public ?string $reviewedAt = null;
    public ?string $notes = null;
}
