<?php

declare(strict_types=1);

namespace App\Leaves;

use App\Core\Container;
use App\Model\LeaveRequest;
use App\Repository\LeaveRequestRepository;

/**
 * Ferie e permessi: la decisione di chi approva, e per l'organizer chi e'
 * via in un giorno (tutto il giorno o qualche ora).
 */
final class LeaveService
{
    private LeaveRequestRepository $leaves;

    public function __construct(Container $container)
    {
        $this->leaves = $container->get(LeaveRequestRepository::class);
    }

    public function decide(int $leaveId, bool $approve, int $userId, string $reply = ''): LeaveRequest
    {
        $leave = $this->leaves->find($leaveId);
        if (!$leave instanceof LeaveRequest || $leave->stage !== 'richiesta') {
            throw new \InvalidArgumentException('Questa richiesta e\' gia\' stata decisa.');
        }
        $this->leaves->update($leaveId, [
            'stage' => $approve ? 'approvata' : 'rifiutata',
            'decided_by' => $userId,
            'decided_at' => date('Y-m-d H:i:s'),
            'reply' => trim($reply) !== '' ? mb_substr(trim($reply), 0, 255) : null,
        ]);

        return $this->leaves->find($leaveId);
    }

    /**
     * L'assenza di una persona in un giorno: tutta la giornata, o dalle/alle.
     *
     * @return ?array{full:bool, from:?int, to:?int, label:string} minuti dalla mezzanotte
     */
    public function absenceFor(int $userId, string $date): ?array
    {
        $result = null;
        foreach ($this->leaves->findApprovedBetween($date, $date, $userId) as $leave) {
            if (!$leave->isPartial()) {
                return ['full' => true, 'from' => null, 'to' => null, 'label' => self::label($leave)];
            }
            [$h1, $m1] = array_map('intval', explode(':', (string) $leave->startTime));
            [$h2, $m2] = array_map('intval', explode(':', (string) $leave->endTime));
            $result = ['full' => false, 'from' => $h1 * 60 + $m1, 'to' => $h2 * 60 + $m2, 'label' => self::label($leave) . ' ' . $leave->startTime . '-' . $leave->endTime];
        }

        return $result;
    }

    public static function label(LeaveRequest $leave): string
    {
        return match ($leave->kind) {
            'ferie' => 'In ferie',
            'malattia' => 'In malattia',
            'permesso', 'ROL' => ucfirst((string) $leave->kind),
            default => 'Assente',
        };
    }
}
