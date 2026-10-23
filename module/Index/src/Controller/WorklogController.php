<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\WorkOrderTypeStep;
use App\Package\PackageCatalogService;
use App\Prompt\Tool\ShowCheckinTool;
use App\Prompt\Tool\ShowWorkInsightsTool;
use App\Prompt\Tool\ShowWorklogTool;
use App\Repository\WorkOrderTypeStepRepository;
use App\Service\AuthService;
use App\Work\WorkOrderOverviewService;
use App\Worklog\CheckinService;

/**
 * Registro attivita': "cosa hai fatto?" (/registro/check-in), il proprio
 * registro (/registro), il pannello dei titolari (/registro/andamento) e
 * lo stato per l'avviso discreto che compare quando e' il momento.
 */
final class WorklogController extends AuthController
{
    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'insights', 'applyTemplate' => 'worklog.team',
            default => 'worklog.view',
        };
    }

    public function weekAction(): ?string
    {
        $components = $this->container->get(ShowWorklogTool::class)->execute(['week' => (string) $this->request->get('settimana', '')]);

        return $this->renderPage($components, ['title' => 'Il mio registro']);
    }

    public function checkinAction(): ?string
    {
        $tool = $this->container->get(ShowCheckinTool::class);
        if ($this->request->getMethod() !== 'POST') {
            $components = $tool->execute(['date' => (string) $this->request->get('giorno', ''), 'period' => (string) $this->request->get('periodo', '')]);

            return $this->renderPage($components, ['title' => 'Cosa hai fatto']);
        }

        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        $date = is_array($body) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($body['date'] ?? '')) === 1 ? (string) $body['date'] : date('Y-m-d');
        if ($date > date('Y-m-d')) {
            $this->json(['ok' => false, 'message' => 'Non si segna il lavoro di un giorno che deve ancora arrivare.'], 422);

            return null;
        }
        $period = in_array($body['period'] ?? null, ['mattina', 'pomeriggio', 'giornata'], true) ? (string) $body['period'] : 'giornata';
        $rows = [];
        foreach ((array) ($body['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'taskId' => isset($row['taskId']) && is_numeric($row['taskId']) ? (int) $row['taskId'] : null,
                'title' => (string) ($row['title'] ?? ''),
                'minutes' => (int) ($row['minutes'] ?? 0),
                'state' => (string) ($row['state'] ?? ''),
                'customerId' => isset($row['customerId']) && is_numeric($row['customerId']) ? (int) $row['customerId'] : null,
            ];
        }

        $userId = (int) $this->container->get(AuthService::class)->currentUserId();
        $result = $this->container->get(CheckinService::class)->save($userId, $date, $period, $rows);

        $message = $result['entries'] > 0
            ? 'Segnato: ' . WorkOrderOverviewService::duration($result['minutes']) . ' di lavoro' . ($result['closed'] > 0 ? ", {$result['closed']} " . ($result['closed'] === 1 ? "attivita' chiusa" : "attivita' chiuse") : '') . '. Grazie!'
            : 'Fatto: niente da segnare per questo pezzo di giornata.';

        $components = $this->container->get(ShowWorklogTool::class)->execute(['week' => $date]);
        array_unshift($components, ['type' => 'notice', 'tone' => 'success', 'text' => $message]);
        $this->json(['ok' => true, 'message' => $message, 'components' => $components]);

        return null;
    }

    /** Per l'avviso discreto ("cosa hai fatto stamattina?"): solo se e' il momento. */
    public function statusAction(): void
    {
        $userId = (int) $this->container->get(AuthService::class)->currentUserId();
        $this->json(['due' => $this->container->get(CheckinService::class)->due($userId)]);
    }

    public function insightsAction(): ?string
    {
        return $this->renderPage($this->container->get(ShowWorkInsightsTool::class)->execute([]), ['title' => 'Tempi e andamento']);
    }

    /** Aggiorna il tempo previsto di una fase del modello con quello vero. */
    public function applyTemplateAction(): void
    {
        if (!in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            $this->json(['ok' => false, 'message' => 'Non ci sono modelli di commessa.'], 404);

            return;
        }
        $steps = $this->container->get(WorkOrderTypeStepRepository::class);
        $step = $steps->find((int) $this->request->get('fase', 0));
        $minutes = (int) $this->request->get('minuti', 0);
        if (!$step instanceof WorkOrderTypeStep || $minutes < 15 || $minutes > 6000) {
            $this->json(['ok' => false, 'message' => 'Fase o tempo non validi.'], 422);

            return;
        }
        $steps->update((int) $step->id, ['estimated_minutes' => $minutes]);
        $this->json(['ok' => true, 'message' => "\u{ab}{$step->title}\u{bb}: ora nel modello sono previste " . WorkOrderOverviewService::duration($minutes) . '. Vale per le prossime commesse.']);
    }
}
