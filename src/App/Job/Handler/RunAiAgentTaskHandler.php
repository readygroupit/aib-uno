<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Core\Container;
use App\Job\JobHandlerInterface;
use App\Repository\AiAgentTaskRepository;
use App\Service\ClaudeService;

/**
 * Handler generico per job_type 'run_ai_agent_task': collega 'scheduler'
 * a 'ai_agent_tasks' senza che nessuno dei due sappia dell'altro (vedi
 * le note di sessione sul perche' sono pacchetti separati). Chiama
 * davvero Claude per proporre un'azione (ClaudeService::suggestAction(),
 * nessun tool: qui non deve mai eseguire nulla di suo, solo scrivere un
 * suggerimento) - primo scorcio reale di comportamento agente, non piu'
 * solo la creazione di un record vuoto. Se Claude non risponde (API non
 * configurata, senza credito, rete giu'), il task viene comunque creato
 * senza proposta: resta visibile e gestibile a mano, il fallimento di
 * Claude non deve far fallire l'intero job.
 *
 * Payload atteso: {agentCode, entityType, entityId, inputContext?,
 * requiresApproval?} - requiresApproval di default true (il default
 * piu' sicuro, coerente con "supervisione umana" del documento Assilevi:
 * se non specificato, meglio chiedere conferma che eseguire da soli).
 */
final class RunAiAgentTaskHandler implements JobHandlerInterface
{
    private AiAgentTaskRepository $repository;
    private ClaudeService $claude;

    public function __construct(Container $container)
    {
        $this->repository = $container->get(AiAgentTaskRepository::class);
        $this->claude = $container->get(ClaudeService::class);
    }

    public function handle(array $payload): array
    {
        foreach (['agentCode', 'entityType', 'entityId'] as $required) {
            if (!isset($payload[$required])) {
                return ['status' => 'failed', 'result' => "Campo mancante nel payload: {$required}"];
            }
        }

        $requiresApproval = $payload['requiresApproval'] ?? true;

        $suggestedAction = null;
        try {
            $suggestedAction = $this->claude->suggestAction($this->buildPrompt($payload));
        } catch (\Throwable $e) {
            // Non blocca la creazione del task: resta 'pending' senza
            // proposta, un operatore puo' comunque vederlo e agire a
            // mano. Non e' un errore del job in se', e' Claude non
            // raggiungibile in questo momento.
        }

        $id = $this->repository->insert([
            'agent_code' => $payload['agentCode'],
            'entity_type' => $payload['entityType'],
            'entity_id' => $payload['entityId'],
            'input_context' => isset($payload['inputContext']) ? json_encode($payload['inputContext']) : null,
            'suggested_action' => $suggestedAction,
            'requires_approval' => $requiresApproval ? 1 : 0,
            'approval_status' => $requiresApproval ? 'pending' : 'not_required',
        ]);

        return ['status' => 'success', 'result' => "ai_agent_tasks.id={$id}"];
    }

    private function buildPrompt(array $payload): string
    {
        $prompt = "Task per l'agente '{$payload['agentCode']}' sull'entita' '{$payload['entityType']}' "
            . "#{$payload['entityId']}.";

        if (isset($payload['inputContext'])) {
            $prompt .= ' Contesto: ' . json_encode($payload['inputContext'], JSON_UNESCAPED_UNICODE);
        }

        return $prompt;
    }
}
