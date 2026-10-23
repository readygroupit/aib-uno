<?php

declare(strict_types=1);

/**
 * Punto di ingresso pensato per un vero cron di sistema (es. ogni minuto:
 * `* * * * * php8.4 /var/www/<progetto>/bin/run-scheduler.php`), non per
 * lo sviluppo interattivo. Registra qui gli handler disponibili per
 * questo progetto (uno per ogni job_type usato) e delega tutto a
 * JobRunner. Un progetto senza il pacchetto 'scheduler' installato non
 * ha le tabelle su cui questo script lavora - va eseguito solo se
 * installato.
 */

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');

require ROOT_PATH . '/autoload.php';

use App\Job\JobHandlerRegistry;
use App\Job\JobRunner;

/** @var \App\Core\Container $container */
$container = require CONFIG_PATH . '/bootstrap.php';

$registry = $container->get(JobHandlerRegistry::class);
$registry->register('run_ai_agent_task', \App\Job\Handler\RunAiAgentTaskHandler::class);
// Job di sistema: si programmano da soli la prima volta (ensureRecurring).
$installed = \App\Package\PackageCatalogService::installedNames();
$scheduledJobs = $container->get(\App\Repository\ScheduledJobRepository::class);
if (in_array('appointments', $installed, true)) {
    $registry->register('sync_google_calendar', \App\Job\Handler\SyncGoogleCalendarHandler::class);
    $scheduledJobs->ensureRecurring('sync_google_calendar', 5);
}
if (in_array('customer_services', $installed, true)) {
    $registry->register('renew_customer_services', \App\Job\Handler\RenewCustomerServicesHandler::class);
    $scheduledJobs->ensureRecurring('renew_customer_services', 1440);
}
if (in_array('installments', $installed, true) && in_array('connectors', $installed, true)) {
    $registry->register('charge_due_installments', \App\Job\Handler\ChargeDueInstallmentsHandler::class);
    $scheduledJobs->ensureRecurring('charge_due_installments', 1440);
}
if (in_array('media_library', $installed, true)) {
    $registry->register('sync_media_library', \App\Job\Handler\SyncMediaLibraryHandler::class);
    $scheduledJobs->ensureRecurring('sync_media_library', 60);
    $registry->register('describe_media', \App\Job\Handler\DescribeMediaHandler::class);
    $scheduledJobs->ensureRecurring('describe_media', 10);
}

// registrare qui gli altri handler reali del progetto, es.:
// $registry->register('send_weekly_report', \App\Job\Handler\SendWeeklyReportHandler::class);

$runner = $container->get(JobRunner::class);
$outcome = $runner->runDueJobs();

fwrite(
    STDOUT,
    sprintf(
        "[%s] eseguiti: %d, falliti: %d\n",
        date('Y-m-d H:i:s'),
        count($outcome['ranJobIds']),
        count($outcome['failedJobIds'])
    )
);
