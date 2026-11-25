<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Assistant\SeasonalCalendar;
use App\Controller\AbstractEntityController;
use App\Model\SeasonalEvent;
use App\Prompt\Tool\EditSeasonalEventTool;
use App\Prompt\Tool\ShowAssistantTool;
use App\Repository\SeasonalEventRepository;
use App\Support\ItalianFormat as F;

/** Il calendario dei periodi forti: quando arrivano, con quanto anticipo, per chi contano. */
final class SeasonalEventsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return SeasonalEventRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditSeasonalEventTool::class;
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
        return 'seasonal_events';
    }

    protected function listPageTitle(): string
    {
        return 'Periodi forti';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo periodo';
    }

    protected function editPageTitle(): string
    {
        return 'Periodo forte';
    }

    protected function defaultsOnCreate(): array
    {
        return ['is_enabled' => '1', 'lead_days' => '30'];
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return 'assistant.manage';
    }

    public function indexAction(): ?string
    {
        $calendar = $this->container->get(SeasonalCalendar::class);
        $today = date('Y-m-d');
        $rows = [];
        foreach ($calendar->events() as $event) {
            $date = $calendar->nextDate($event, $today);
            $rows[] = ['date' => $date ?? '9999', 'row' => [
                'title' => (string) $event->name,
                'href' => '/assistente/stagioni/' . $event->id,
                'meta' => ($date !== null ? F::longDate($date) . ' (' . F::relativeDays($date, $today) . ')' : 'data da completare')
                    . " \u{b7} " . (int) $event->leadDays . ' giorni prima'
                    . " \u{b7} " . mb_substr((string) $event->keywords, 0, 90),
                'badge' => (int) $event->isEnabled === 1 ? null : ['label' => 'spento', 'tone' => 'neutral'],
            ]];
        }
        usort($rows, static fn (array $a, array $b) => $a['date'] <=> $b['date']);

        return $this->renderPage([[
            'type' => 'simple-board',
            'title' => 'Periodi forti',
            'createHref' => '/assistente/stagioni/nuovo',
            'createLabel' => 'Periodo',
            'stats' => [],
            'sections' => [[
                'title' => "Quando l'assistente propone di prepararsi",
                'tone' => 'neutral',
                'rows' => array_column($rows, 'row'),
                'empty' => 'Nessun periodo.',
            ]],
        ]], ['title' => 'Periodi forti']);
    }
}
