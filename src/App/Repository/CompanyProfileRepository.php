<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CompanyProfile;

final class CompanyProfileRepository extends AbstractRepository
{
    protected string $table = 'company_profiles';
    protected string $modelClass = CompanyProfile::class;

    /** I dati dell'azienda che emette le fatture (una riga sola). */
    public function current(): ?CompanyProfile
    {
        $found = $this->findAll([], 'id DESC');

        return $found[0] ?? null;
    }
}
