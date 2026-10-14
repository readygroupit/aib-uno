<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Repository\AgentMessageRepository;
use App\Service\AgentService;
use App\View\Component\AgentActivityComponent;
use App\View\Component\DashboardHeaderComponent;
use App\View\Component\StatBoxComponent;

/**
 * Operato dei colleghi digitali: cosa hanno fatto questa settimana e la
 * precedente (messaggi, azioni completate, in attesa del tuo OK). Prima il
 * dato che conta (azioni completate), poi i contatori, poi agente per
 * agente. La configurazione degli agenti resta su ShowAgentsTool.
 */
final class ShowAgentActivityTool implements PromptToolInterface
{
    private const MESI = ['', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function name(): string
    {
        return 'show_agent_activity';
    }

    public function description(): string
    {
        return "Mostra l'operato degli agenti AI (colleghi digitali): cosa hanno fatto questa settimana e la "
            . "precedente, agente per agente - segnalazioni, azioni completate, in attesa di approvazione, "
            . "lette o ignorate. Usalo quando l'utente chiede cosa hanno fatto o prodotto gli agenti, il loro operato, "
            . "lavoro, attivita', risultati o un confronto tra settimane.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Operato agenti';
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
        return 'agents.view';
    }

    public function triggers(): array
    {
        return ['operato agenti', 'operato degli agenti', 'cosa hanno fatto gli agenti', 'attivita agenti'];
    }

    public function execute(array $input): array
    {
        $messages = $this->container->get(AgentMessageRepository::class);
        $statBox = $this->container->get(StatBoxComponent::class);

        $today = date('Y-m-d', strtotime('+1 day'));
        $weekStart = date('Y-m-d', strtotime('-6 days'));
        $lastWeekStart = date('Y-m-d', strtotime('-13 days'));
        $current = $messages->countByAgentAndStateBetween($weekStart, $today);
        $previous = $messages->countByAgentAndStateBetween($lastWeekStart, $weekStart);

        $sum = static function (array $rows, ?string $state = null, ?int $agentId = null): int {
            $total = 0;
            foreach ($rows as $row) {
                if (($state === null || $row['state'] === $state) && ($agentId === null || $row['agent_id'] === $agentId)) {
                    $total += $row['total'];
                }
            }

            return $total;
        };

        $agents = [];
        foreach ($this->container->get(AgentService::class)->agents() as $agent) {
            $id = (int) $agent->id;
            $agents[] = [
                'code' => (string) $agent->code,
                'name' => (string) $agent->name,
                'role' => (string) $agent->role,
                'thisWeek' => $sum($current, null, $id),
                'lastWeek' => $sum($previous, null, $id),
                'done' => $sum($current, 'done', $id),
                'pending' => $sum($current, 'pending', $id),
                'dismissed' => $sum($current, 'dismissed', $id),
            ];
        }

        $doneNow = $sum($current, 'done');
        $donePrev = $sum($previous, 'done');
        $from = strtotime('-6 days');
        $period = (int) date('j', $from) . (date('n', $from) !== date('n') ? ' ' . self::MESI[(int) date('n', $from)] : '')
            . ' - ' . (int) date('j') . ' ' . self::MESI[(int) date('n')];

        return [
            $this->container->get(DashboardHeaderComponent::class)->toData([
                'dateLabel' => mb_strtoupper('Operato dei colleghi digitali · ' . $period),
                'greeting' => 'Cosa hanno fatto per te',
            ]),
            $statBox->toData([
                'label' => 'Azioni completate',
                'value' => (string) $doneNow,
                'comparison' => 'questa settimana · settimana scorsa: ' . $donePrev,
                'feature' => true,
                'badge' => self::delta($doneNow, $donePrev),
                'sparkline' => $messages->countPerDayLast7ByState('done'),
                'sparklineStyle' => 'bars',
                'href' => '/agenti',
            ]),
            $statBox->toData([
                'label' => 'Segnalazioni',
                'value' => (string) $sum($current),
                'comparison' => 'questa settimana · settimana scorsa: ' . $sum($previous),
                'dotColor' => 'teal',
            ]),
            $statBox->toData([
                'label' => 'Da gestire',
                'value' => (string) $sum($current, 'pending'),
                'comparison' => 'segnalazioni aperte, le trovi in home',
                'dotColor' => 'brass',
                'href' => '/',
            ]),
            $statBox->toData([
                'label' => 'Ignorate',
                'value' => (string) $sum($current, 'dismissed'),
                'comparison' => 'segnalazioni che hai scartato',
                'dotColor' => 'lilac',
            ]),
            $statBox->toData([
                'label' => 'Agenti attivi',
                'value' => count(array_filter($agents, static fn (array $a) => $a['thisWeek'] > 0)) . ' / ' . count($agents),
                'comparison' => 'hanno lavorato questa settimana',
                'dotColor' => 'moss',
                'href' => '/agenti',
            ]),
            $this->container->get(AgentActivityComponent::class)->toData([
                'title' => 'Agente per agente',
                'subtitle' => 'Questa settimana contro la precedente.',
                'agents' => $agents,
            ]),
        ];
    }

    private static function delta(int $now, int $before): ?string
    {
        if ($before === 0) {
            return $now > 0 ? 'nuove questa settimana' : null;
        }

        return sprintf('%+d%% vs sett. prec.', (int) round(($now - $before) * 100 / $before));
    }
}
