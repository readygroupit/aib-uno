<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerNote extends AbstractModel
{
    public ?int $customerId = null;
    public ?int $workOrderId = null;
    public ?string $kind = null;
    public ?string $title = null;
    public ?string $body = null;
    public ?string $notedAt = null;
    public ?string $source = null;
    public ?int $onRoadmap = null;
    public ?string $roadmapStatus = null;
    public ?string $dueDate = null;
    public ?string $mentionedUserIds = null;
    public ?int $authorUserId = null;
}
