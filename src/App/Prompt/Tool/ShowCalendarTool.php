<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Calendar\CalendarOverviewService;
use App\Calendar\CalendarSyncService;
use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\AppointmentRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\CalendarWeekComponent;

/**
 * Il calendario: la settimana (o l'elenco dei prossimi giorni) con uscite,
 * incontri e call. Sostituisce il vecchio elenco degli appuntamenti.
 */
final class ShowCalendarTool implements PromptToolInterface, PromptConversationalInterface
{
    private CalendarOverviewService $overview;
    private CalendarSyncService $sync;
    private AppointmentRepository $appointments;
    private CalendarWeekComponent $component;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(CalendarOverviewService::class);
        $this->sync = $container->get(CalendarSyncService::class);
        $this->appointments = $container->get(AppointmentRepository::class);
        $this->component = $container->get(CalendarWeekComponent::class);
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'show_calendar';
    }

    public function description(): string
    {
        return "Calendario della settimana: shooting, riprese, incontri, call, con chi c'e' e dove. "
            . "'week' (una data della settimana, AAAA-MM-GG) per un'altra settimana, 'view' = 'elenco' per i prossimi 14 giorni. "
            . "Usalo per 'calendario', 'agenda', 'appuntamenti', 'uscite della settimana', 'cosa abbiamo la prossima settimana'.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'week' => ['type' => 'string', 'description' => 'Una data qualunque della settimana da mostrare (AAAA-MM-GG)'],
                'view' => ['type' => 'string', 'enum' => ['settimana', 'elenco']],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'Calendario';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        $today = date('Y-m-d');
        $count = count(array_filter(
            $this->appointments->findBetween("{$today} 00:00:00", date('Y-m-d', strtotime('+1 day')) . ' 00:00:00'),
            static fn ($a) => $a->isActive()
        ));

        return $count > 0 ? F::plural($count, 'oggi', 'oggi') : null;
    }

    public function requiredPermission(): ?string
    {
        return 'appointments.view';
    }

    public function triggers(): array
    {
        return ['calendario', 'agenda', 'appuntamenti', 'uscite', 'la mia agenda'];
    }

    public function execute(array $input): array
    {
        $date = isset($input['week']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['week']) === 1 ? (string) $input['week'] : date('Y-m-d');
        $monday = CalendarOverviewService::weekStart($date);
        $view = ($input['view'] ?? 'settimana') === 'elenco' ? 'elenco' : 'settimana';

        $week = $this->overview->week($monday);

        return [$this->component->toData([
            'url' => '/appuntamenti' . ($view === 'elenco' ? '?vista=elenco' : ($monday !== CalendarOverviewService::weekStart(date('Y-m-d')) ? '?settimana=' . $monday : '')),
            'view' => $view,
            'week' => $week,
            'agenda' => $view === 'elenco' ? $this->overview->agenda(date('Y-m-d')) : [],
            'meId' => $this->auth->currentUserId(),
            'google' => $this->sync->status(),
            'createHref' => '/appuntamenti/nuovo',
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $today = date('Y-m-d');
        $todayList = array_values(array_filter(
            $this->appointments->findBetween("{$today} 00:00:00", date('Y-m-d', strtotime('+1 day')) . ' 00:00:00'),
            static fn ($a) => $a->isActive()
        ));
        $week = $this->overview->week(CalendarOverviewService::weekStart($today));

        $sentence = $todayList === []
            ? 'Oggi nessun appuntamento.'
            : 'Oggi: ' . implode(', ', array_map(static fn ($a) => date('H:i', strtotime((string) $a->startAt)) . " \u{ab}{$a->title}\u{bb}", array_slice($todayList, 0, 3))) . (count($todayList) > 3 ? ' e altri ' . (count($todayList) - 3) : '') . '.';
        $sentence .= ' In settimana: ' . $week['summary'] . '.';
        if ($week['conflicts'] > 0) {
            $sentence .= ' Attenzione: ' . F::plural($week['conflicts'], 'appuntamento si sovrappone', 'appuntamenti si sovrappongono') . ' per le stesse persone.';
        }

        return $sentence;
    }

    public function followUpSuggestions(): array
    {
        return ['Calendario della prossima settimana', 'Le mie attivita', 'Commesse'];
    }
}
