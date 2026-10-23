<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Core\Container;
use App\Job\JobHandlerInterface;
use App\Media\MediaLibraryService;
use App\Service\AiService;

/**
 * job_type 'describe_media': fa descrivere dall'IA i file in attesa, 15
 * per volta (ogni file e' una chiamata), ogni 10 minuti. Senza IA
 * configurata non fa nulla.
 */
final class DescribeMediaHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        if (!$this->container->get(AiService::class)->isConfigured()) {
            return ['status' => 'success', 'result' => 'IA non configurata: niente da fare.'];
        }
        $outcome = $this->container->get(MediaLibraryService::class)->analyzePending(15);

        return ['status' => 'success', 'result' => "Descritti: {$outcome['done']}, non riusciti: {$outcome['failed']}, ancora in attesa: {$outcome['left']}"];
    }
}
