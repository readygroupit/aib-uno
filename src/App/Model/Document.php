<?php

declare(strict_types=1);

namespace App\Model;

final class Document extends AbstractModel
{
    public ?string $externalId = null;
    public ?int $folderId = null;
    public ?string $name = null;
    public ?string $mimeType = null;
    public ?int $sizeBytes = null;
    public ?string $webLink = null;
    public ?string $md5 = null;
    public ?string $driveModifiedAt = null;
    public ?string $description = null;
    public ?string $tags = null;
    public ?int $customerId = null;
    public ?int $workOrderId = null;
    public ?int $uploadedBy = null;
    public ?string $origin = null;
    public ?string $trashedAt = null;
    public ?string $syncedAt = null;
    public ?int $taskId = null;
    public ?string $localPath = null;
}
