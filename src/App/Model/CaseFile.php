<?php

declare(strict_types=1);

namespace App\Model;

/**
 * 'CaseFile', non 'Case': 'case' e' una parola riservata del linguaggio
 * PHP (switch/match), non usabile come nome di classe.
 */
final class CaseFile extends AbstractModel
{
    public ?string $title = null;
    public ?string $category = null;
    public ?string $stage = null;
    public ?int $priority = null;
    public ?int $assignedUserId = null;
    public ?int $primaryCustomerId = null;
    public ?int $leadId = null;
    public ?string $openedAt = null;
    public ?string $closedAt = null;
    public ?string $notes = null;
}
