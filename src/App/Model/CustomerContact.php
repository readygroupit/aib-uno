<?php

declare(strict_types=1);

namespace App\Model;

final class CustomerContact extends AbstractModel
{
    public ?int $customerId = null;
    public ?string $fullName = null;
    public ?string $role = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?int $isDecisionMaker = null;
    public ?string $notes = null;
}
