<?php

declare(strict_types=1);

namespace App\Job;

/**
 * Contratto di un handler per un 'job_type' dello scheduler. handle()
 * riceve il payload decodificato del job e deve tornare un esito -
 * nessuna eccezione non gestita: JobRunner la trasforma comunque in un
 * esito 'failed' per non bloccare gli altri job dovuti, ma un handler
 * che intercetta i propri errori produce un 'result' piu' utile per il
 * log in scheduled_job_runs.
 */
interface JobHandlerInterface
{
    /**
     * @param array $payload payload del job, gia' json_decode-ato
     * @return array{status: string, result?: string} status: 'success'|'failed'
     */
    public function handle(array $payload): array;
}
