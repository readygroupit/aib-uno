<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerTeamMember extends AbstractModel
{
    public ?int $customerId = null;
    public ?int $userId = null;
    public ?string $role = null;
    public ?string $notes = null;
}
