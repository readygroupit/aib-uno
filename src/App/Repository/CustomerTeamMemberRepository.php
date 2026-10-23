<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\CustomerTeamMember;

final class CustomerTeamMemberRepository extends AbstractRepository
{
    protected string $table = 'customer_team_members';
    protected string $modelClass = CustomerTeamMember::class;

    /** @return CustomerTeamMember[] */
    public function findByCustomer(int $customerId): array
    {
        return $this->findAll(['customer_id' => $customerId], "FIELD(role, 'referente', 'social', 'grafica', 'video', 'sviluppo', 'adv', 'strategia', 'altro'), id");
    }

    /** @return int[] i clienti in cui la persona e' in squadra */
    public function customerIdsForUser(int $userId): array
    {
        return array_values(array_unique(array_map(static fn (CustomerTeamMember $m) => (int) $m->customerId, $this->findAll(['user_id' => $userId]))));
    }
}
