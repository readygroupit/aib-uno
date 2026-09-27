<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Profile;

final class ProfileRepository extends AbstractRepository
{
    protected string $table = 'profiles';
    protected string $modelClass = Profile::class;
}
