<?php

declare(strict_types=1);

namespace App\Model;

final class Appointment extends AbstractModel
{
    public ?string $title = null;
    public ?string $kind = null;
    public ?string $startAt = null;
    public ?string $endAt = null;
    public ?string $location = null;
    public ?int $travelMinutes = null;
    public ?int $isOnline = null;
    public ?string $stage = null;
    public ?int $assignedUserId = null;
    public ?string $attendeeUserIds = null;
    public ?int $customerId = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
    public ?string $checklist = null;
    public ?int $reminderMinutesBefore = null;
    public ?string $notes = null;
    public ?string $meetUrl = null;
    public ?string $googleEventId = null;
    public ?string $googleSyncedAt = null;
    public ?string $source = null;

    /** Fine dichiarata, o un'ora dopo l'inizio. */
    public function end(): string
    {
        return $this->endAt ?? date('Y-m-d H:i:s', strtotime((string) $this->startAt) + 3600);
    }

    /** @return int[] responsabile e colleghi, senza doppioni */
    public function peopleIds(): array
    {
        $ids = array_map('intval', array_filter(explode(',', (string) $this->attendeeUserIds), static fn (string $v) => trim($v) !== ''));
        if ($this->assignedUserId !== null) {
            array_unshift($ids, (int) $this->assignedUserId);
        }

        return array_values(array_unique($ids));
    }

    public function isActive(): bool
    {
        return $this->stage !== 'annullato';
    }
}
