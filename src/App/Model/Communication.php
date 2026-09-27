<?php

declare(strict_types=1);

namespace App\Model;

final class Communication extends AbstractModel
{
    public ?string $entityType = null;
    public ?int $entityId = null;
    public ?string $channel = null;
    public ?string $direction = null;
    public ?string $deliveryStatus = null;
    public ?string $templateCode = null;
    public ?string $sentAt = null;
    public ?string $readAt = null;
}
