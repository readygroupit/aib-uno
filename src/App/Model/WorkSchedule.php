<?php

declare(strict_types=1);

namespace App\Model;

final class WorkSchedule extends AbstractModel
{
    public ?int $userId = null;
    public ?string $startTime = null;
    public ?string $endTime = null;
    public ?string $breakStart = null;
    public ?string $breakEnd = null;
    public ?string $workdays = null;
    public ?int $focusPercent = null;

    /** Orario di chi non ne ha uno: 9-18, pausa 13-14, lunedi'-venerdi', 85% pianificabile. */
    public static function standard(int $userId): self
    {
        return self::fromArray([
            'user_id' => $userId,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_start' => '13:00',
            'break_end' => '14:00',
            'workdays' => '1,2,3,4,5',
            'focus_percent' => 85,
        ]);
    }

    public function worksOn(string $date): bool
    {
        $days = array_filter(array_map('trim', explode(',', (string) ($this->workdays ?: '1,2,3,4,5'))));

        return in_array(date('N', strtotime($date)), $days, true);
    }

    /** Minuti dalla mezzanotte, da "09:30". */
    public static function minutes(?string $time, int $default): int
    {
        if ($time === null || preg_match('/^(\d{1,2}):(\d{2})/', $time, $m) !== 1) {
            return $default;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
