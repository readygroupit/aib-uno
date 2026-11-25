<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Assistant\AssistantService;
use App\Core\Container;
use App\Job\JobHandlerInterface;

/**
 * job_type 'run_assistant': ogni ora, di giorno (7-20), l'assistente fa il
 * suo giro: avvisi una volta per episodio, solleciti preparati o mandati.
 * Di notte non scrive a nessuno.
 */
final class RunAssistantHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $hour = (int) date('G');
        if (empty($payload['force']) && ($hour < 7 || $hour > 20)) {
            return ['status' => 'success', 'result' => 'Fuori orario: nessun giro.'];
        }
        $result = $this->container->get(AssistantService::class)->run();

        return ['status' => 'success', 'result' => "Avvisi: {$result['notified']}, messaggi preparati: {$result['proposed']}, mandati da solo: {$result['sent']}"];
    }
}
