<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Core\Container;
use App\Job\JobHandlerInterface;
use App\Social\SocialPublisher;

/**
 * job_type 'publish_social_posts': i contenuti programmati arrivati
 * all'ora di uscita escono su Facebook/Instagram (vedi SocialPublisher).
 * Ogni 5 minuti; senza programmati non fa nulla.
 */
final class PublishSocialPostsHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $outcome = $this->container->get(SocialPublisher::class)->publishDue();

        return ['status' => 'success', 'result' => "Pubblicati: {$outcome['published']}, non riusciti: {$outcome['failed']}"];
    }
}
