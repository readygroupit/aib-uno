<?php

declare(strict_types=1);

namespace App\Model;

final class AssistantAction extends AbstractModel
{
    public ?string $ruleCode = null;
    public ?int $userId = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
    public ?string $dedupeKey = null;
    public ?string $title = null;
    public ?string $body = null;
    public ?string $emailTo = null;
    public ?string $emailSubject = null;
    public ?string $emailBody = null;
    public ?string $stage = null;
    public ?string $decidedAt = null;
    public ?int $decidedBy = null;
}
