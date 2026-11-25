<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Connector\ConnectorException;
use App\Core\Container;
use App\Documents\DocumentService;
use App\Job\JobHandlerInterface;

/** job_type 'sync_documents': ogni 15 minuti, le novita' del Drive nei Documenti. Senza Drive collegato non fa nulla. */
final class SyncDocumentsHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $service = $this->container->get(DocumentService::class);
        if (!$service->isReady()) {
            return ['status' => 'success', 'result' => 'Google Drive non collegato'];
        }
        try {
            $result = $service->sync(!empty($payload['full']));
        } catch (ConnectorException $e) {
            return ['status' => 'failed', 'result' => $e->getMessage()];
        }

        return ['status' => 'success', 'result' => "Giro ({$result['mode']}): {$result['folders']} cartelle, {$result['files']} file, {$result['trashed']} tolti"];
    }
}
