<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\ClientRequests\EmailRequestImporter;
use App\Connector\ConnectorException;
use App\Core\Container;
use App\Job\JobHandlerInterface;

/**
 * job_type 'import_email_requests': le email nuove dei clienti (Gmail)
 * diventano richieste da smistare. Ogni 5 minuti; senza Gmail collegato
 * non fa nulla.
 */
final class ImportEmailRequestsHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $importer = $this->container->get(EmailRequestImporter::class);
        if (!$importer->isReady()) {
            return ['status' => 'success', 'result' => 'Gmail non collegato'];
        }
        try {
            $outcome = $importer->import();
        } catch (ConnectorException $e) {
            return ['status' => 'failed', 'result' => $e->getMessage()];
        }

        return ['status' => 'success', 'result' => "Richieste nuove: {$outcome['created']}, risposte: {$outcome['replies']}, saltate: {$outcome['skipped']}"];
    }
}
