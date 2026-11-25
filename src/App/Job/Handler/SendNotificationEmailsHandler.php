<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Core\Container;
use App\Job\JobHandlerInterface;
use App\Notifications\NotificationService;

/** job_type 'send_notification_emails': gli avvisi da mandare per email, raggruppati per persona. Ogni 5 minuti. */
final class SendNotificationEmailsHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $sent = $this->container->get(NotificationService::class)->sendDueEmails();

        return ['status' => 'success', 'result' => "Email di avviso inviate: {$sent}"];
    }
}
