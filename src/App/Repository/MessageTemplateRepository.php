<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\MessageTemplate;

final class MessageTemplateRepository extends AbstractRepository
{
    protected string $table = 'message_templates';
    protected string $modelClass = MessageTemplate::class;
}
