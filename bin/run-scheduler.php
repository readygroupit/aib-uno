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
