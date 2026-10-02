<?php

declare(strict_types=1);

namespace App\Model;

final class MessageTemplate extends AbstractModel
{
    public ?string $code = null;
    public ?string $name = null;
    public ?string $channel = null;
    public ?string $subject = null;
    public ?string $body = null;
}
