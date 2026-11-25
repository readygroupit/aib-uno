<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Core\Config;
use App\Core\Container;
use App\Model\Notification;
use App\Model\NotificationPreference;
use App\Package\PackageCatalogService;
use App\Repository\NotificationPreferenceRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\MailService;

/**
 * Avvisare un collega: nel gestionale e/o per email secondo le sue
 * preferenze. Senza il pacchetto 'notifications' non fa nulla, cosi' chi
 * chiama non deve controllarlo ogni volta.
 */
final class NotificationService
{
    private Container $container;
    private bool $enabled;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->enabled = in_array('notifications', PackageCatalogService::installedNames(), true);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Chi ha fatto l'azione non riceve l'avviso di cio' che ha appena fatto.
     *
     * @return bool se l'avviso e' stato registrato
     */
    public function notify(?int $userId, string $type, string $title, ?string $body = null, ?string $url = null, ?string $entityType = null, ?int $entityId = null): bool
    {
        if (!$this->enabled || $userId === null || $userId <= 0) {
            return false;
        }
        if ($userId === $this->container->get(AuthService::class)->currentUserId()) {
            return false;
        }

        [$inApp, $email] = $this->channels($userId, $type);
        if (!$inApp && !$email) {
            return false;
        }
        // Lo stesso avviso ancora da leggere non si ripete (es. "tocca a te" su una fase gia' segnalata).
        if ($this->notifications()->hasUnread($userId, $type, $title, $url)) {
            return false;
        }

        $this->notifications()->insert([
            'user_id' => $userId,
            'type' => $type,
            'title' => mb_substr($title, 0, 200),
            'body' => $body,
            'url' => $url,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'in_app' => $inApp ? 1 : 0,
            'email_due' => $email ? 1 : 0,
        ]);

        return true;
    }

    /** @return array{0:bool,1:bool} nel gestionale, per email */
    public function channels(int $userId, string $type): array
    {
        $defaults = NotificationTypes::ALL[$type] ?? ['in_app' => true, 'email' => false];
        $preference = $this->preferences()->forUser($userId)[$type] ?? null;
        if (!$preference instanceof NotificationPreference) {
            return [$defaults['in_app'], $defaults['email']];
        }

        return [(int) $preference->inApp === 1, (int) $preference->email === 1];
    }

    /** @return array<string, array{type:string,label:string,help:string,in_app:bool,email:bool}> */
    public function preferencesFor(int $userId): array
    {
        $result = [];
        foreach (NotificationTypes::ALL as $type => $meta) {
            [$inApp, $email] = $this->channels($userId, $type);
            $result[$type] = ['type' => $type, 'label' => $meta['label'], 'help' => $meta['help'], 'in_app' => $inApp, 'email' => $email];
        }

        return $result;
    }

    /** @param array<string, array{in_app:bool,email:bool}> $choices */
    public function savePreferences(int $userId, array $choices): void
    {
        $existing = $this->preferences()->forUser($userId);
        foreach (NotificationTypes::ALL as $type => $meta) {
            $choice = $choices[$type] ?? ['in_app' => false, 'email' => false];
            $data = ['in_app' => $choice['in_app'] ? 1 : 0, 'email' => $choice['email'] ? 1 : 0];
            if (isset($existing[$type])) {
                $this->preferences()->update((int) $existing[$type]->id, $data);
            } else {
                $this->preferences()->insert(['user_id' => $userId, 'type' => $type] + $data);
            }
        }
    }

    public function unreadCount(int $userId): int
    {
        return $this->enabled ? $this->notifications()->unreadCount($userId) : 0;
    }

    /** @return Notification[] */
    public function recent(int $userId, int $limit = 50): array
    {
        return $this->enabled ? $this->notifications()->recentForUser($userId, $limit) : [];
    }

    /** Aperto un avviso: letto. Solo i propri. */
    public function open(int $notificationId, int $userId): ?Notification
    {
        $notification = $this->notifications()->find($notificationId);
        if (!$notification instanceof Notification || (int) $notification->userId !== $userId) {
            return null;
        }
        if ($notification->readAt === null) {
            $this->notifications()->update($notificationId, ['read_at' => date('Y-m-d H:i:s')]);
        }

        return $notification;
    }

    public function markAllRead(int $userId): int
    {
        return $this->notifications()->markAllRead($userId);
    }

    /**
     * Le email in attesa, una per persona con tutti i suoi avvisi: dieci
     * avvisi di fila non devono diventare dieci email.
     *
     * @return int email inviate
     */
    public function sendDueEmails(): int
    {
        if (!$this->enabled) {
            return 0;
        }
        $byUser = [];
        foreach ($this->notifications()->findEmailDue() as $notification) {
            $byUser[(int) $notification->userId][] = $notification;
        }

        $users = $this->container->get(UserRepository::class);
        $mail = $this->container->get(MailService::class);
        $base = self::appUrl($this->container->get(Config::class));
        $appName = defined('APP_NAME') ? APP_NAME : 'il gestionale';
        $sent = 0;

        foreach ($byUser as $userId => $items) {
            $user = $users->find($userId);
            $ids = array_map(static fn (Notification $n) => (int) $n->id, $items);
            if ($user === null || trim((string) $user->email) === '') {
                $this->notifications()->markEmailed($ids);
                continue;
            }
            $lines = [];
            foreach ($items as $n) {
                $lines[] = '- ' . $n->title
                    . ($n->body !== null && $n->body !== '' ? "\n  " . str_replace("\n", "\n  ", $n->body) : '')
                    . ($n->url !== null ? "\n  " . $base . $n->url : '');
            }
            $subject = count($items) === 1 ? (string) $items[0]->title : count($items) . ' novita\' per te su ' . $appName;
            $name = trim((string) $user->firstName) !== '' ? (string) $user->firstName : (string) $user->username;
            $body = "Ciao {$name},\n\n" . implode("\n\n", $lines)
                . "\n\nScegli quali avvisi ricevere per email da {$base}/avvisi/preferenze";
            if ($mail->send((string) $user->email, $subject, $body)) {
                $sent++;
            }
            $this->notifications()->markEmailed($ids);
        }

        return $sent;
    }

    /** Indirizzo del gestionale per i link nelle email: 'app.url', altrimenti quello del provisioning. */
    public static function appUrl(Config $config): string
    {
        $url = (string) ($config->get('app.url') ?? '');
        if ($url === '') {
            $pattern = (string) ($config->get('provisioning.urlPattern') ?? 'http://%s.localhost');
            $url = sprintf($pattern, (string) ($config->get('app.slug') ?? 'uno'));
        }

        return rtrim($url, '/');
    }

    private function notifications(): NotificationRepository
    {
        return $this->container->get(NotificationRepository::class);
    }

    private function preferences(): NotificationPreferenceRepository
    {
        return $this->container->get(NotificationPreferenceRepository::class);
    }
}
