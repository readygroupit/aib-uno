<?php

declare(strict_types=1);

namespace App\Model;

final class DocumentFolder extends AbstractModel
{
    public ?string $externalId = null;
    public ?int $parentId = null;
    public ?string $name = null;
    public ?int $customerId = null;
    public ?int $workOrderId = null;
    public ?string $webLink = null;
    public ?string $driveModifiedAt = null;
    public ?string $trashedAt = null;
    public ?string $syncedAt = null;
}
