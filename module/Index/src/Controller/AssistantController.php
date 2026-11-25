<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Assistant\AssistantRules;
use App\Assistant\AssistantService;
use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\AssistantAction;
use App\Prompt\Tool\EditAssistantActionTool;
use App\Prompt\Tool\ShowAssistantTool;
use App\Repository\AssistantActionRepository;
use App\Repository\AssistantRuleRepository;
use App\Service\AuthService;

/**
 * La giornata secondo l'assistente (/assistente), i messaggi che prepara
 * (si rileggono e si mandano: /assistente/azioni/:id) e le sue regole
 * (/assistente/regole, per chi configura il gestionale). Un messaggio lo
 * vede e lo manda solo la persona a cui e' destinato.
 */
final class AssistantController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return AssistantActionRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditAssistantActionTool::class;
    }

    protected function listToolClass(): string
    {
        return ShowAssistantTool::class;
    }

    protected function packageName(): string
    {
        return 'assistant';
    }

    protected function manifestEntityKey(): string
    {
        return 'assistant_actions';
    }

    protected function listPageTitle(): string
    {
        return 'Assistente';
    }

    protected function newPageTitle(): string
    {
        return 'Assistente';
    }

    protected function editPageTitle(): string
    {
        return 'Messaggio per il cliente';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'rules' => 'assistant.manage',
            'postpone' => 'customers.view',
            'new', 'delete' => 'users.manage',
            default => null,
        };
    }

    public function indexAction(): ?string
    {
        return $this->renderPage($this->container->get(ShowAssistantTool::class)->execute([
            'agent' => (string) $this->request->get('agente', ''),
            'view' => $this->request->get('vista') === 'giornata' ? 'agenda' : 'agents',
        ]), ['title' => 'I tuoi agenti']);
    }

    public function editAction(): ?string
    {
        $action = $this->repository()->find((int) $this->param('id'));
        if (!$action instanceof AssistantAction || (int) $action->userId !== $this->userId()) {
            $this->redirect('/assistente');

            return null;
        }

        return parent::editAction();
    }

    /** Salvare il messaggio e' mandarlo. */
    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        return $this->container->get(AssistantService::class)->send($id, $this->userId());
    }

    /** "Prepara il messaggio" dalla giornata. */
    public function prepareAction(): void
    {
        $action = $this->container->get(AssistantService::class)->prepareByKey(
            $this->userId(),
            (string) $this->request->get('rule', ''),
            (string) $this->request->get('key', '')
        );
        if ($action === null) {
            $this->json(['ok' => false, 'message' => "Non c'e' piu' niente da preparare qui."], 404);

            return;
        }
        $this->json(['ok' => true, 'open' => '/assistente/azioni/' . $action->id, 'message' => 'Messaggio pronto: rileggilo.']);
    }

    public function dismissAction(): void
    {
        $action = $this->repository()->find((int) $this->param('id'));
        $ok = $action instanceof AssistantAction && (int) $action->userId === $this->userId()
            && $this->container->get(AssistantService::class)->dismiss((int) $action->id, $this->userId());
        $this->json(['ok' => $ok, 'message' => $ok ? 'Va bene, non lo mando.' : 'Gia\' deciso.']);
    }

    /** "Rimanda" un cliente da risentire: un altro giro della sua temperatura. */
    public function postponeAction(): void
    {
        $date = $this->container->get(\App\Customers\CustomerFollowUpService::class)->postpone((int) $this->request->get('customer', 0));
        $this->json($date !== null ? ['ok' => true, 'message' => 'Rimandato al ' . date('d/m/Y', strtotime($date)) . '.'] : ['ok' => false, 'message' => 'Cliente non trovato.']);
    }

    /** Le regole: accese o spente, i giorni, cosa puo' fare da solo. */
    public function rulesAction(): ?string
    {
        $service = $this->container->get(AssistantService::class);
        $rows = $service->rulesForSettings();
        if ($this->request->getMethod() === 'POST') {
            $repo = $this->container->get(AssistantRuleRepository::class);
            foreach ($rows as $row) {
                $code = $row['code'];
                $data = ['is_enabled' => (string) $this->request->get($code . '__enabled', '0') === '1' ? 1 : 0];
                if ($row['days'] !== null) {
                    $days = (int) $this->request->get($code . '__days', (string) $row['current']['days']);
                    $data['days'] = max(0, min(365, $days));
                }
                if ($row['autonomy'] !== null) {
                    $autonomy = (string) $this->request->get($code . '__autonomy', (string) $row['current']['autonomy']);
                    $data['autonomy'] = in_array($autonomy, [AssistantRules::AUTONOMY_NOTICE, AssistantRules::AUTONOMY_CONFIRM, AssistantRules::AUTONOMY_AUTO], true) ? $autonomy : $row['autonomy'];
                }
                $repo->update($row['id'], $data);
            }
            $this->json(['ok' => true, 'message' => 'Regole salvate.']);

            return null;
        }

        // Le regole raggruppate per agente, nell'ordine degli agenti.
        $agentOf = [];
        $order = [];
        foreach (AssistantRules::COLLEAGUES as $key => $meta) {
            foreach ($meta['rules'] as $i => $code) {
                $agentOf[$code] = ['key' => $key, 'name' => $meta['name'], 'role' => $meta['role'], 'color' => $meta['color']];
                $order[$code] = count($order);
            }
        }
        usort($rows, static fn (array $a, array $b) => ($order[$a['code']] ?? 99) <=> ($order[$b['code']] ?? 99));

        return $this->renderPage([[
            'type' => 'assistant-rules',
            'title' => "Regole dell'assistente",
            'action' => '/assistente/regole',
            'seasonsHref' => '/assistente/stagioni',
            'autonomies' => [AssistantRules::AUTONOMY_NOTICE, AssistantRules::AUTONOMY_CONFIRM, AssistantRules::AUTONOMY_AUTO],
            'rows' => array_map(static fn (array $r) => [
                'agent' => $agentOf[$r['code']] ?? null,
                'code' => $r['code'],
                'label' => $r['label'],
                'help' => $r['help'],
                'enabled' => $r['current']['enabled'],
                'days' => $r['days'] !== null ? $r['current']['days'] : null,
                'daysLabel' => $r['daysLabel'],
                'autonomy' => $r['autonomy'] !== null ? $r['current']['autonomy'] : null,
            ], $rows),
        ]], ['title' => "Regole dell'assistente"]);
    }

    private function userId(): int
    {
        return (int) $this->container->get(AuthService::class)->currentUserId();
    }
}
