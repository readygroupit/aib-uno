<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\NotificationPreference;

final class NotificationPreferenceRepository extends AbstractRepository
{
    protected string $table = 'notification_preferences';
    protected string $modelClass = NotificationPreference::class;

    /** @return array<string, NotificationPreference> tipo => preferenza */
    public function forUser(int $userId): array
    {
        $result = [];
        foreach ($this->findAll(['user_id' => $userId]) as $preference) {
            $result[(string) $preference->type] = $preference;
        }

        return $result;
    }
}
