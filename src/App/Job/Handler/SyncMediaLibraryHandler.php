<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Core\Container;
use App\Job\JobHandlerInterface;
use App\Media\MediaLibraryService;

/**
 * job_type 'sync_media_library': legge le origini dell'archivio (Drive,
 * cartella) e aggiorna l'indice; i file nuovi restano "da analizzare" per
 * DescribeMediaHandler. Ogni ora.
 */
final class SyncMediaLibraryHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $outcome = $this->container->get(MediaLibraryService::class)->sync();

        return ['status' => 'success', 'result' => "Nuovi: {$outcome['added']}, cambiati: {$outcome['changed']}, tolti: {$outcome['removed']}"];
    }
}
