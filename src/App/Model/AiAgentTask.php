<?php

declare(strict_types=1);

namespace App\Model;

final class AiAgentTask extends AbstractModel
{
    public ?string $agentCode = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
    public ?string $inputContext = null;
    public ?string $suggestedAction = null;
    /** TINYINT(1) come nel resto del framework (vedi AbstractModel::$status): 0/1, non bool - PDO/MySQL non lo mappano a bool. */
    public ?int $requiresApproval = null;
    public ?string $approvalStatus = null;
    public ?int $approvedByUserId = null;
    public ?string $approvedAt = null;
    public ?string $executedAt = null;
}
