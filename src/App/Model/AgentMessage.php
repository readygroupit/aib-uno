<?php

declare(strict_types=1);

namespace App\Model;

final class AgentMessage extends AbstractModel
{
    public ?int $agentId = null;
    public ?string $dedupeKey = null;
    public ?string $body = null;
    public ?string $doneBody = null;
    public ?string $detail = null;
    public ?string $actionType = null;
    public ?string $actionLabel = null;
    public ?string $linkHref = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
    public ?string $state = null;
    public ?string $resultText = null;
}
