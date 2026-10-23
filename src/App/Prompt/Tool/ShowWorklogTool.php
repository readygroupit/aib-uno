<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Service\AuthService;
use App\View\Component\WorklogWeekComponent;
use App\Worklog\CheckinService;
use App\Worklog\WorklogOverviewService;

final class ShowWorklogTool implements PromptToolInterface
{
    private WorklogOverviewService $overview;
    private CheckinService $checkins;
    private AuthService $auth;
    private WorklogWeekComponent $component;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(WorklogOverviewService::class);
        $this->checkins = $container->get(CheckinService::class);
        $this->auth = $container->get(AuthService::class);
        $this->component = $container->get(WorklogWeekComponent::class);
    }

    public function name(): string
    {
        return 'show_worklog';
    }

    public function description(): string
    {
        return "Il proprio registro attivita' della settimana: cosa si e' fatto giorno per giorno e quanto tempo. Usalo per 'il mio registro', 'le mie ore'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['week' => ['type' => 'string', 'description' => 'Una data della settimana (AAAA-MM-GG)']]];
    }

    public function menuLabel(): ?string
    {
        return 'Il mio registro';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return 'worklog.view';
    }

    public function triggers(): array
    {
        return ['il mio registro', 'registro', 'le mie ore'];
    }

    public function execute(array $input): array
    {
        $date = isset($input['week']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['week']) === 1 ? (string) $input['week'] : date('Y-m-d');
        $monday = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $userId = (int) $this->auth->currentUserId();
        $week = $this->overview->myWeek($userId, $monday);
        $due = $this->checkins->due($userId);

        return [$this->component->toData($week + [
            'url' => '/registro' . ($monday !== date('Y-m-d', strtotime('monday this week')) ? '?settimana=' . $monday : ''),
            'title' => 'Il mio registro',
            'weekLabel' => 'Settimana dal ' . (int) date('j', strtotime($monday)) . ' ' . \App\Support\ItalianFormat::monthName((int) date('n', strtotime($monday))),
            'prev' => '/registro?settimana=' . date('Y-m-d', strtotime("{$monday} -7 days")),
            'next' => '/registro?settimana=' . date('Y-m-d', strtotime("{$monday} +7 days")),
            'checkinHref' => '/registro/check-in',
            'checkinLabel' => $due !== null ? $due['question'] : 'Segna il lavoro',
            'due' => $due !== null,
            'addHref' => '/registro/voci/nuovo?user_id=' . $userId . '&work_date=' . date('Y-m-d'),
            'insightsHref' => $this->auth->hasPermission('worklog.team') ? '/registro/andamento' : null,
        ])];
    }
}
