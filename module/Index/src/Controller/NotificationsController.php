<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\Notification;
use App\Notifications\NotificationService;
use App\Notifications\NotificationTypes;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;

/** I propri avvisi (campanella) e quali riceverne, nel gestionale o per email. Ognuno vede solo i suoi. */
final class NotificationsController extends AuthController
{
    public function indexAction(): ?string
    {
        $userId = $this->userId();
        $today = date('Y-m-d');
        $items = array_map(static fn (Notification $n) => [
            'title' => (string) $n->title,
            'body' => $n->body,
            'typeLabel' => NotificationTypes::label((string) $n->type),
            'when' => F::relativeDays(substr((string) $n->createdAt, 0, 10), $today) . ' alle ' . substr((string) $n->createdAt, 11, 5),
            'href' => '/avvisi/' . $n->id,
            'unread' => $n->readAt === null,
        ], $this->service()->recent($userId));

        return $this->renderPage([[
            'type' => 'notification-list',
            'title' => 'Avvisi',
            'unread' => $this->service()->unreadCount($userId),
            'items' => $items,
            'readAllAction' => '/avvisi/tutti-letti',
            'preferencesHref' => '/avvisi/preferenze',
        ]], ['title' => 'Avvisi']);
    }

    public function countAction(): void
    {
        $this->json(['unread' => $this->service()->unreadCount($this->userId())]);
    }

    /** Aperto un avviso: letto, e si va dove dice. */
    public function openAction(): void
    {
        $notification = $this->service()->open((int) $this->param('id'), $this->userId());
        $this->redirect($notification !== null && $notification->url !== null ? (string) $notification->url : '/avvisi');
    }

    public function readAllAction(): void
    {
        $count = $this->service()->markAllRead($this->userId());
        $this->json(['ok' => true, 'message' => $count > 0 ? 'Tutti letti.' : 'Niente da segnare.']);
    }

    public function preferencesAction(): ?string
    {
        $userId = $this->userId();
        if ($this->request->getMethod() === 'POST') {
            $choices = [];
            foreach (array_keys(NotificationTypes::ALL) as $type) {
                $choices[$type] = [
                    'in_app' => (string) $this->request->get($type . '__in_app', '0') === '1',
                    'email' => (string) $this->request->get($type . '__email', '0') === '1',
                ];
            }
            $this->service()->savePreferences($userId, $choices);
            $this->json(['ok' => true, 'message' => 'Preferenze salvate.']);

            return null;
        }

        return $this->renderPage([[
            'type' => 'notification-preferences',
            'title' => 'Quali avvisi ricevere',
            'action' => '/avvisi/preferenze',
            'rows' => array_values($this->service()->preferencesFor($userId)),
        ]], ['title' => 'Avvisi']);
    }

    private function userId(): int
    {
        return (int) $this->container->get(AuthService::class)->currentUserId();
    }

    private function service(): NotificationService
    {
        return $this->container->get(NotificationService::class);
    }
}
