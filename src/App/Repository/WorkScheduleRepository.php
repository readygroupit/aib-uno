<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\WorkSchedule;

final class WorkScheduleRepository extends AbstractRepository
{
    protected string $table = 'work_schedules';
    protected string $modelClass = WorkSchedule::class;

    /** L'orario della persona, o quello standard se non ne ha uno. */
    public function forUser(int $userId): WorkSchedule
    {
        $found = $this->findAll(['user_id' => $userId], 'id DESC');

        return $found[0] ?? WorkSchedule::standard($userId);
    }
}
