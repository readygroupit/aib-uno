<?php

declare(strict_types=1);

namespace App\Job;

use App\Core\Container;

/**
 * Mappa un codice 'job_type' (colonna scheduled_jobs.job_type) alla
 * classe handler che lo esegue - stesso principio del registro
 * componenti JS di Fase 2 (registerComponent/renderComponent), qui lato
 * server: il motore non conosce i job_type in anticipo, li risolve a
 * runtime. Registrazione esplicita (niente scansione/autodiscovery di
 * file, coerente con la scelta gia' fatta per il DI container).
 */
final class JobHandlerRegistry
{
    /** @var array<string,class-string<JobHandlerInterface>> */
    private array $handlers = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function register(string $jobType, string $handlerClass): void
    {
        $this->handlers[$jobType] = $handlerClass;
    }

    public function resolve(string $jobType): JobHandlerInterface
    {
        if (!isset($this->handlers[$jobType])) {
            throw new \RuntimeException("Nessun handler registrato per job_type '{$jobType}'");
        }

        return $this->container->get($this->handlers[$jobType]);
    }
}
