<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Appointment;

final class AppointmentRepository extends AbstractRepository
{
    protected string $table = 'appointments';
    protected string $modelClass = Appointment::class;

    /** @return Appointment[] che toccano l'intervallo [from, to), in ordine di inizio */
    public function findBetween(string $from, string $to): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM appointments
             WHERE status != 0 AND start_at < :to AND COALESCE(end_at, start_at + INTERVAL 1 HOUR) > :from
             ORDER BY start_at, id",
            ['from' => $from, 'to' => $to]
        );

        return array_map(static fn (array $row) => Appointment::fromArray($row), $rows);
    }

    /** @return Appointment[] da adesso in poi, non annullati */
    public function findUpcomingForCustomer(int $customerId, int $limit = 5): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM appointments
             WHERE status != 0 AND customer_id = :c AND stage != 'annullato' AND COALESCE(end_at, start_at) >= :now
             ORDER BY start_at LIMIT {$limit}",
            ['c' => $customerId, 'now' => date('Y-m-d H:i:s')]
        );

        return array_map(static fn (array $row) => Appointment::fromArray($row), $rows);
    }

    /** @return Appointment[] collegati a un elemento (es. una commessa) */
    public function findForEntity(string $entityType, int $entityId): array
    {
        return $this->findAll(['entity_type' => $entityType, 'entity_id' => $entityId], 'start_at');
    }

    public function findByGoogleEventId(string $eventId): ?Appointment
    {
        $row = $this->db->fetchRow('SELECT * FROM appointments WHERE google_event_id = :id ORDER BY status DESC LIMIT 1', ['id' => $eventId]);

        return $row !== null ? Appointment::fromArray($row) : null;
    }

    /** @return Appointment[] da mandare a Google: mai sincronizzati o modificati dopo l'ultima sincronizzazione */
    public function findToPush(string $since): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM appointments
             WHERE (google_synced_at IS NULL OR updated_at > google_synced_at)
               AND COALESCE(end_at, start_at) >= :since
               AND (status != 0 OR google_event_id IS NOT NULL)
             ORDER BY start_at",
            ['since' => $since]
        );

        return array_map(static fn (array $row) => Appointment::fromArray($row), $rows);
    }
}
