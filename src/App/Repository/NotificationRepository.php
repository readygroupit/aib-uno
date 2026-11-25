<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Notification;

final class NotificationRepository extends AbstractRepository
{
    protected string $table = 'notifications';
    protected string $modelClass = Notification::class;

    public function unreadCount(int $userId): int
    {
        $row = $this->db->fetchRow('SELECT COUNT(*) AS n FROM notifications WHERE status != 0 AND in_app = 1 AND user_id = :u AND read_at IS NULL', ['u' => $userId]);

        return (int) ($row['n'] ?? 0);
    }

    public function hasUnread(int $userId, string $type, string $title, ?string $url): bool
    {
        return $this->db->fetchRow(
            'SELECT 1 FROM notifications WHERE status != 0 AND user_id = :u AND type = :t AND title = :title AND url <=> :url
               AND read_at IS NULL AND (emailed_at IS NULL OR in_app = 1) LIMIT 1',
            ['u' => $userId, 't' => $type, 'title' => mb_substr($title, 0, 200), 'url' => $url]
        ) !== null;
    }

    /** @return Notification[] i piu' recenti della campanella */
    public function recentForUser(int $userId, int $limit): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM notifications WHERE status != 0 AND user_id = :u AND in_app = 1
             ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit),
            ['u' => $userId]
        );

        return array_map(static fn (array $row) => Notification::fromArray($row), $rows);
    }

    public function markAllRead(int $userId): int
    {
        return $this->db->execute('UPDATE notifications SET read_at = :now WHERE status != 0 AND user_id = :u AND read_at IS NULL', ['now' => date('Y-m-d H:i:s'), 'u' => $userId]);
    }

    /** @return Notification[] */
    public function findEmailDue(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM notifications WHERE status != 0 AND email_due = 1 AND emailed_at IS NULL ORDER BY user_id, created_at, id LIMIT 500');

        return array_map(static fn (array $row) => Notification::fromArray($row), $rows);
    }

    /** @param int[] $ids */
    public function markEmailed(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db->execute("UPDATE notifications SET emailed_at = ? WHERE id IN ({$placeholders})", [date('Y-m-d H:i:s'), ...$ids]);
    }

    /** @param string[] $types @return Notification[] gli avvisi di quei tipi dati alla persona negli ultimi giorni */
    public function recentOfTypes(int $userId, array $types, int $days, int $limit): array
    {
        if ($types === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $rows = $this->db->fetchAll(
            "SELECT * FROM notifications WHERE status != 0 AND user_id = ? AND created_at >= ? AND type IN ({$placeholders}) ORDER BY created_at DESC, id DESC LIMIT " . max(1, $limit),
            [$userId, date('Y-m-d H:i:s', strtotime("-{$days} days")), ...$types]
        );

        return array_map(static fn (array $row) => Notification::fromArray($row), $rows);
    }
}
