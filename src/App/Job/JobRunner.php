<?php

declare(strict_types=1);

namespace App\Job;

use App\Core\Container;
use App\Model\ScheduledJob;
use App\Repository\ScheduledJobRepository;
use App\Repository\ScheduledJobRunRepository;

/**
 * Motore dello scheduler: trova i job dovuti (App\Repository\ScheduledJobRepository::findDue()),
 * li esegue tramite JobHandlerRegistry, registra l'esito in
 * scheduled_job_runs. Pensato per essere chiamato da un cron reale una
 * volta al minuto (vedi bin/run-scheduler.php) - non schedula nulla da
 * solo, e' il passo "adesso controlla ed esegui", non il timer.
 *
 * Un job una tantum (interval_minutes NULL) viene disattivato dopo
 * l'esecuzione (status=0, lo stesso soft delete del framework - non
 * serve un flag "completato" separato). Un job ricorrente avanza
 * run_at di interval_minutes rispetto al run_at precedente, non da
 * "adesso": cosi' un job ogni 60 minuti resta agganciato al suo orario
 * anche se il runner e' rimasto fermo per un po' - ma senza rincorrere
 * tutte le esecuzioni perse (si riallinea al primo slot futuro, non
 * genera una raffica di esecuzioni arretrate).
 *
 * Nessuna query qui dentro: tutto l'accesso a scheduled_jobs/
 * scheduled_job_runs passa dai rispettivi repository, stessa disciplina
 * di ogni altro Service del framework (vedi App\Service\AuthService).
 */
final class JobRunner
{
    private ScheduledJobRepository $jobs;
    private ScheduledJobRunRepository $runs;
    private JobHandlerRegistry $registry;

    public function __construct(Container $container)
    {
        $this->jobs = $container->get(ScheduledJobRepository::class);
        $this->runs = $container->get(ScheduledJobRunRepository::class);
        $this->registry = $container->get(JobHandlerRegistry::class);
    }

    /**
     * @return array{ranJobIds: int[], failedJobIds: int[]}
     */
    public function runDueJobs(): array
    {
        $ranJobIds = [];
        $failedJobIds = [];

        foreach ($this->jobs->findDue() as $job) {
            $outcome = $this->runOne($job);

            if ($outcome === 'success') {
                $ranJobIds[] = $job->id;
            } else {
                $failedJobIds[] = $job->id;
            }
        }

        return ['ranJobIds' => $ranJobIds, 'failedJobIds' => $failedJobIds];
    }

    private function runOne(ScheduledJob $job): string
    {
        $startedAt = date('Y-m-d H:i:s');
        $payload = json_decode((string) $job->payload, true) ?? [];

        try {
            $handler = $this->registry->resolve((string) $job->jobType);
            $outcome = $handler->handle($payload);
            $status = $outcome['status'] ?? 'failed';
            $result = $outcome['result'] ?? null;
        } catch (\Throwable $e) {
            $status = 'failed';
            $result = $e->getMessage();
        }

        $finishedAt = date('Y-m-d H:i:s');

        $this->runs->insert([
            'scheduled_job_id' => $job->id,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'run_status' => $status,
            'result' => $result,
        ]);

        $update = [
            'last_run_at' => $finishedAt,
            'last_run_status' => $status,
        ];

        if (empty($job->intervalMinutes)) {
            $update['status'] = 0;
        } else {
            $nextRun = strtotime((string) $job->runAt) + ($job->intervalMinutes * 60);
            $now = time();
            $update['run_at'] = date('Y-m-d H:i:s', $nextRun < $now ? $now : $nextRun);
        }

        $this->jobs->update($job->id, $update);

        return $status;
    }
}
