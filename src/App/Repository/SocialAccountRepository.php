<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\SocialAccount;

final class SocialAccountRepository extends AbstractRepository
{
    protected string $table = 'social_accounts';
    protected string $modelClass = SocialAccount::class;

    /** L'account di un cliente su un social ('facebook' o 'instagram'), se collegato. */
    public function findForCustomer(int $customerId, string $network): ?SocialAccount
    {
        $rows = $this->findAll(['customer_id' => $customerId, 'network' => $network], 'id');

        return $rows[0] ?? null;
    }

    public function findByExternal(string $network, string $externalId): ?SocialAccount
    {
        $rows = $this->findAll(['network' => $network, 'external_id' => $externalId], 'id', true);

        return $rows[0] ?? null;
    }
}
