<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Calendar\CalendarSyncService;
use App\Core\Container;
use App\Job\JobHandlerInterface;

/**
 * job_type 'sync_google_calendar': appuntamenti <-> Google Calendar, ogni
 * pochi minuti (interval_minutes 5-10). Manda quelli rimasti indietro (es.
 * Google non rispondeva al salvataggio) e legge le modifiche fatte su
 * Google.
 */
final class SyncGoogleCalendarHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $result = $this->container->get(CalendarSyncService::class)->syncAll();

        return [
            'status' => $result['error'] === null ? 'success' : 'failed',
            'result' => $result['error'] ?? "Inviati {$result['pushed']}, aggiornati da Google {$result['pulled']}",
        ];
    }
}
