<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\ShowAgentsTool;
use App\Service\AgentService;
use App\View\Component\AgentFeedComponent;

/**
 * La pagina Agenti + gli endpoint JSON usati dal flusso in home e dalla
 * pagina stessa (vedi agent-feed.js / agents-board.js). Ambiente
 * dimostrativo: vedi App\Service\AgentService.
 */
final class AgentsController extends AuthController
{
    protected function requiredPermissionForAction(string $action): ?string
    {
        return in_array($action, ['index', 'feed'], true) ? 'agents.view' : 'agents.edit';
    }

    public function indexAction(): ?string
    {
        $components = $this->container->get(ShowAgentsTool::class)->execute([]);

        return $this->renderPage($components, ['title' => 'Agenti']);
    }

    public function feedAction(): void
    {
        $this->json($this->container->get(AgentFeedComponent::class)->toData([]));
    }

    public function approveAction(): void
    {
        $result = $this->service()->approve((int) $this->param('id'));
        $this->json($result === null ? ['ok' => false] : ['ok' => true, 'result' => $result], $result === null ? 422 : 200);
    }

    public function dismissAction(): void
    {
        $this->json(['ok' => $this->service()->dismiss((int) $this->param('id'))]);
    }

    public function runAction(): void
    {
        $created = $this->service()->run((int) $this->param('id'));
        $this->json(['ok' => true, 'created' => $created]);
    }

    public function restartAction(): void
    {
        $this->json(['ok' => true, 'created' => $this->service()->restart()]);
    }

    public function saveAction(): void
    {
        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        $agent = $this->service()->save((int) $this->param('id'), is_array($body) ? $body : []);
        $this->json(['ok' => $agent !== null], $agent === null ? 404 : 200);
    }

    private function service(): AgentService
    {
        return $this->container->get(AgentService::class);
    }
}
