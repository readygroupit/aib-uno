<?php

declare(strict_types=1);

namespace App\Model;

final class CommunicationContent extends AbstractModel
{
    public ?int $communicationId = null;
    public ?string $subject = null;
    public ?string $body = null;
}
