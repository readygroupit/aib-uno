<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Connector\ConnectorException;
use App\Connector\ConnectorRegistry;
use App\Connector\GoogleCalendarConnector;
use App\Core\Container;
use App\Model\Appointment;
use App\Package\PackageCatalogService;
use App\Repository\AppointmentRepository;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;

/**
 * Appuntamenti <-> Google Calendar.
 *
 * Verso Google: ogni appuntamento nuovo o cambiato diventa un evento (con i
 * colleghi invitati, il promemoria, il link Meet per le videochiamate); uno
 * eliminato o annullato sparisce dal calendario.
 * Da Google: un evento creato o spostato su Google aggiorna (o crea)
 * l'appuntamento; uno cancellato lo segna annullato.
 *
 * Chi vince se cambiano entrambi: l'ultima modifica (updated di Google
 * contro google_synced_at). Se Google non risponde, l'appuntamento resta
 * "da sincronizzare" e ci riprova il job (RunGoogleCalendarSyncHandler).
 */
final class CalendarSyncService
{
    private AppointmentRepository $appointments;
    private UserRepository $users;
    private ?GoogleCalendarConnector $google = null;
    private ?CustomerRepository $customers = null;

    public function __construct(Container $container)
    {
        $this->appointments = $container->get(AppointmentRepository::class);
        $this->users = $container->get(UserRepository::class);
        $installed = PackageCatalogService::installedNames();
        if (in_array('connectors', $installed, true)) {
            $registry = $container->get(ConnectorRegistry::class);
            if ($registry->has('google_calendar')) {
                $connector = $registry->get('google_calendar');
                $this->google = $connector instanceof GoogleCalendarConnector ? $connector : null;
            }
        }
        if (in_array('customers', $installed, true)) {
            $this->customers = $container->get(CustomerRepository::class);
        }
    }

    public function isEnabled(): bool
    {
        return $this->google !== null && $this->google->isConnected();
    }

    /** Per la testata del calendario: collegato, quando, eventuale errore. */
    public function status(): array
    {
        if ($this->google === null) {
            return ['available' => false, 'connected' => false];
        }
        $state = $this->google->isConnected() ? $this->google->syncState() : [];

        return [
            'available' => true,
            'connected' => $this->google->isConnected(),
            'summary' => $this->google->summary(),
            'lastSyncAt' => $state['lastSyncAt'] ?? null,
            'lastError' => $state['lastError'] ?? null,
        ];
    }

    /**
     * Manda a Google un appuntamento appena salvato. Non lancia: se Google
     * non risponde torna il messaggio da mostrare, il job riprovera'.
     */
    public function push(int $appointmentId): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }
        $appointment = $this->appointments->find($appointmentId, true);
        if (!$appointment instanceof Appointment) {
            return null;
        }

        try {
            $this->pushOne($appointment);

            return null;
        } catch (ConnectorException $e) {
            return 'Google Calendar non ha risposto: riprovo da solo fra qualche minuto.';
        }
    }

    /** @return array{pushed:int, pulled:int, error:?string} */
    public function syncAll(): array
    {
        if (!$this->isEnabled()) {
            return ['pushed' => 0, 'pulled' => 0, 'error' => null];
        }

        $pushed = 0;
        $pulled = 0;
        $error = null;
        try {
            foreach ($this->appointments->findToPush(date('Y-m-d H:i:s', strtotime('-7 days'))) as $appointment) {
                $this->pushOne($appointment);
                $pushed++;
            }

            $state = $this->google->syncState();
            $changes = $this->google->changedEvents($state['syncToken']);
            foreach ($changes['events'] as $event) {
                $pulled += $this->pullOne($event) ? 1 : 0;
            }
            $this->google->saveSyncState($changes['syncToken'], null);
        } catch (ConnectorException $e) {
            $error = $e->getMessage();
            $this->google->saveSyncState(null, $error);
        }

        return ['pushed' => $pushed, 'pulled' => $pulled, 'error' => $error];
    }

    private function pushOne(Appointment $appointment): void
    {
        $id = (int) $appointment->id;
        $gone = $appointment->status === 0 || $appointment->stage === 'annullato';

        if ($gone) {
            if ($appointment->googleEventId !== null) {
                $this->google->deleteEvent($appointment->googleEventId);
            }
            $this->markSynced($id, ['google_event_id' => null]);

            return;
        }

        $saved = $this->google->saveEvent($this->toEvent($appointment), $appointment->googleEventId);
        $this->markSynced($id, [
            'google_event_id' => $saved['id'],
            'meet_url' => self::meetLink($saved) ?? $appointment->meetUrl,
        ]);
    }

    /** @return bool se ha cambiato qualcosa qui */
    private function pullOne(array $event): bool
    {
        $eventId = (string) ($event['id'] ?? '');
        if ($eventId === '') {
            return false;
        }
        $localId = isset($event['extendedProperties']['private']['uno_appointment_id']) ? (int) $event['extendedProperties']['private']['uno_appointment_id'] : null;
        $existing = $localId !== null ? $this->appointments->find($localId, true) : null;
        $existing ??= $this->appointments->findByGoogleEventId($eventId);

        if (($event['status'] ?? '') === 'cancelled') {
            if ($existing instanceof Appointment && $existing->stage !== 'annullato' && $existing->status !== 0) {
                $this->appointments->update((int) $existing->id, ['stage' => 'annullato', 'google_synced_at' => date('Y-m-d H:i:s')]);

                return true;
            }

            return false;
        }

        $data = $this->fromEvent($event);
        if ($data === null) {
            return false;
        }

        if ($existing instanceof Appointment) {
            // Cambiato su Google dopo l'ultima sincronizzazione: vince Google.
            $updated = isset($event['updated']) ? date('Y-m-d H:i:s', strtotime((string) $event['updated'])) : null;
            if ($existing->googleSyncedAt !== null && $updated !== null && $updated <= $existing->googleSyncedAt) {
                return false;
            }
            $this->appointments->update((int) $existing->id, $data + ['google_event_id' => $eventId, 'google_synced_at' => date('Y-m-d H:i:s')]);

            return true;
        }

        $this->appointments->insert($data + [
            'kind' => isset($event['hangoutLink']) ? 'call' : 'altro',
            'is_online' => isset($event['hangoutLink']) ? 1 : 0,
            'stage' => 'confermato',
            'source' => 'google',
            'assigned_user_id' => $this->userIdByEmail((string) ($event['organizer']['email'] ?? $event['creator']['email'] ?? '')),
            'google_event_id' => $eventId,
            'google_synced_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    private function toEvent(Appointment $a): array
    {
        $tz = $this->google->timeZone();
        $description = [];
        if ($a->customerId !== null && $this->customers !== null) {
            $customer = $this->customers->find((int) $a->customerId);
            if ($customer !== null) {
                $description[] = 'Cliente: ' . $customer->displayName();
            }
        }
        if ($a->travelMinutes) {
            $description[] = 'Viaggio: ' . $a->travelMinutes . ' minuti per andata';
        }
        $checklist = array_filter(array_map('trim', explode("\n", (string) $a->checklist)));
        if ($checklist !== []) {
            $description[] = "Da portare / da fare:\n- " . implode("\n- ", $checklist);
        }
        if ($a->notes) {
            $description[] = (string) $a->notes;
        }

        $attendees = [];
        foreach ($a->peopleIds() as $userId) {
            $user = $this->users->find($userId);
            if ($user !== null && $user->email) {
                $attendees[] = ['email' => $user->email, 'displayName' => trim("{$user->firstName} {$user->lastName}")];
            }
        }

        $event = [
            'summary' => (string) $a->title,
            'location' => $a->location,
            'description' => implode("\n\n", $description),
            'start' => ['dateTime' => date('c', strtotime((string) $a->startAt)), 'timeZone' => $tz],
            'end' => ['dateTime' => date('c', strtotime($a->end())), 'timeZone' => $tz],
            'attendees' => $attendees,
            'extendedProperties' => ['private' => ['uno_appointment_id' => (string) $a->id]],
            'reminders' => $a->reminderMinutesBefore
                ? ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => (int) $a->reminderMinutesBefore]]]
                : ['useDefault' => true],
        ];

        if ((int) $a->isOnline === 1 && $a->meetUrl === null) {
            $event['conferenceData'] = ['createRequest' => [
                'requestId' => 'uno-' . $a->id . '-' . time(),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ]];
        }

        return $event;
    }

    /** @return ?array campi dell'appuntamento da un evento Google */
    private function fromEvent(array $event): ?array
    {
        $start = $event['start']['dateTime'] ?? null;
        $end = $event['end']['dateTime'] ?? null;
        // Evento di un giorno intero: lo si mette in orario di lavoro.
        if ($start === null && isset($event['start']['date'])) {
            $start = $event['start']['date'] . ' 09:00:00';
            $end = date('Y-m-d', strtotime((string) ($event['end']['date'] ?? $event['start']['date']) . ' -1 day')) . ' 18:00:00';
        }
        if ($start === null) {
            return null;
        }

        return [
            'title' => mb_substr((string) ($event['summary'] ?? '(senza titolo)'), 0, 190),
            'start_at' => date('Y-m-d H:i:s', strtotime((string) $start)),
            'end_at' => $end !== null ? date('Y-m-d H:i:s', strtotime((string) $end)) : null,
            'location' => isset($event['location']) ? mb_substr((string) $event['location'], 0, 190) : null,
            'meet_url' => self::meetLink($event),
        ];
    }

    private static function meetLink(array $event): ?string
    {
        if (isset($event['hangoutLink'])) {
            return (string) $event['hangoutLink'];
        }
        foreach ($event['conferenceData']['entryPoints'] ?? [] as $entry) {
            if (($entry['entryPointType'] ?? '') === 'video' && isset($entry['uri'])) {
                return (string) $entry['uri'];
            }
        }

        return null;
    }

    private function userIdByEmail(string $email): ?int
    {
        if ($email === '') {
            return null;
        }
        foreach ($this->users->findAll() as $user) {
            if (strcasecmp((string) $user->email, $email) === 0) {
                return (int) $user->id;
            }
        }

        return null;
    }

    /**
     * google_synced_at uguale a updated_at (stesso istante): l'appuntamento
     * non risulta piu' "da mandare" (vedi AppointmentRepository::findToPush()).
     */
    private function markSynced(int $id, array $data): void
    {
        $this->appointments->update($id, $data + ['google_synced_at' => date('Y-m-d H:i:s')]);
    }
}
