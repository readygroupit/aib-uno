<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\AgentMessage;

final class AgentMessageRepository extends AbstractRepository
{
    protected string $table = 'agent_messages';
    protected string $modelClass = AgentMessage::class;

    /** @return AgentMessage[] i piu' recenti per primi */
    public function findRecent(int $limit): array
    {
        return $this->paginate(1, $limit, [], 'created_at DESC, id DESC')['items'];
    }

    /**
     * Messaggi per agente e stato in un periodo (operato dei colleghi digitali).
     *
     * @return list<array{agent_id: int, state: string, total: int}>
     */
    public function countByAgentAndStateBetween(string $from, string $to): array
    {
        return array_map(static fn (array $row) => [
            'agent_id' => (int) $row['agent_id'],
            'state' => (string) $row['state'],
            'total' => (int) $row['total'],
        ], $this->db->fetchAll(
            "SELECT agent_id, state, COUNT(*) AS total FROM {$this->table}
             WHERE status != 0 AND created_at >= :from AND created_at < :to
             GROUP BY agent_id, state",
            ['from' => $from, 'to' => $to]
        ));
    }

    /** @return int[] messaggi in uno stato giorno per giorno, ultimi 7 giorni (oggi per ultimo) */
    public function countPerDayLast7ByState(string $state): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS total FROM {$this->table}
             WHERE status != 0 AND state = :state AND created_at >= :since GROUP BY DATE(created_at)",
            ['state' => $state, 'since' => date('Y-m-d', strtotime('-6 days'))]
        );
        $byDay = array_column($rows, 'total', 'd');
        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $series[] = (int) ($byDay[date('Y-m-d', strtotime("-{$i} days"))] ?? 0);
        }

        return $series;
    }
}
