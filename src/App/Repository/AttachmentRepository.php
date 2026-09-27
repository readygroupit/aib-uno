<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Attachment;

final class AttachmentRepository extends AbstractRepository
{
    protected string $table = 'attachments';
    protected string $modelClass = Attachment::class;
}
