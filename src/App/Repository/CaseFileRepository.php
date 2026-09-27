<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CaseFile;

final class CaseFileRepository extends AbstractRepository
{
    protected string $table = 'cases';
    protected string $modelClass = CaseFile::class;
}
