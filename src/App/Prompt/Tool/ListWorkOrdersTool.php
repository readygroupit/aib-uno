<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\WorkOrderRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\StatBoxComponent;
use App\View\Component\WorkOrderBoardComponent;
use App\Work\WorkOrderOverviewService;
use App\Work\WorkOrderService;

final class ListWorkOrdersTool implements PromptToolInterface, PromptConversationalInterface
{
    private WorkOrderOverviewService $overview;
    private WorkOrderRepository $orders;
    private StatBoxComponent $statBox;
    private WorkOrderBoardComponent $board;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(WorkOrderOverviewService::class);
        $this->orders = $container->get(WorkOrderRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->board = $container->get(WorkOrderBoardComponent::class);
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'list_work_orders';
    }

    public function description(): string
    {
        return "Bacheca delle commesse (lavori per i clienti): in corso, in attesa del cliente, in ritardo, ferme, consegnate. "
            . "Usalo per 'commesse', 'lavori in corso', 'cosa aspettiamo dai clienti', 'commesse in ritardo'.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'filter' => ['type' => 'string', 'enum' => ['tutte', 'mie', 'ritardo', 'ferme', 'attesa'], 'description' => 'Scheda da aprire'],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'Commesse';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        return (string) $this->overview->board()['totals']['active'];
    }

    public function requiredPermission(): ?string
    {
        return 'work_orders.view';
    }

    public function triggers(): array
    {
        return ['commesse', 'le commesse', 'lavori in corso', 'commesse in corso'];
    }

    public function execute(array $input): array
    {
        $board = $this->overview->board();
        $t = $board['totals'];

        return [$this->board->toData([
            'title' => 'Commesse',
            'url' => '/commesse',
            'createHref' => '/commesse/nuovo',
            'createLabel' => 'Nuova commessa',
            'typesHref' => '/tipi-commessa',
            'meId' => $this->auth->currentUserId(),
            'filter' => (string) ($input['filter'] ?? 'tutte'),
            'columns' => $board['columns'],
            'stats' => [
                $this->statBox->toData([
                    'label' => 'Commesse aperte',
                    'value' => (string) $t['active'],
                    'comparison' => $t['dueThisWeek'] > 0 ? F::plural($t['dueThisWeek'], 'da consegnare', 'da consegnare') . ' entro 7 giorni' : 'nessuna consegna entro 7 giorni',
                    'feature' => true,
                    'badge' => F::plural($t['deliveredLast30'], 'consegnata', 'consegnate') . ' in 30 gg',
                ]),
                $this->statBox->toData([
                    'label' => 'In attesa del cliente',
                    'value' => (string) $t['waiting'],
                    'comparison' => $t['waiting'] > 0 ? 'la piu\' vecchia da ' . F::plural($t['oldestWaitingDays'], 'giorno', 'giorni') : 'nessuna attesa',
                    'dotColor' => 'brass',
                ]),
                $this->statBox->toData([
                    'label' => 'In ritardo',
                    'value' => (string) $t['late'],
                    'comparison' => $t['late'] > 0 ? 'consegna prevista superata' : 'tutte nei tempi',
                    'dotColor' => $t['late'] > 0 ? 'danger' : 'moss',
                ]),
                $this->statBox->toData([
                    'label' => 'Ferme',
                    'value' => (string) $t['stale'],
                    'comparison' => WorkOrderService::STALE_DAYS . '+ giorni senza movimenti',
                    'dotColor' => $t['stale'] > 0 ? 'lilac' : 'moss',
                ]),
            ],
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $t = $this->overview->board()['totals'];
        if ($t['active'] === 0) {
            return 'Nessuna commessa aperta.';
        }
        $parts = [];
        if ($t['waiting'] > 0) {
            $parts[] = F::plural($t['waiting'], 'aspetta', 'aspettano') . " il cliente (la piu' vecchia da " . F::plural($t['oldestWaitingDays'], 'giorno', 'giorni') . ')';
        }
        if ($t['late'] > 0) {
            $parts[] = F::plural($t['late'], 'e\' in ritardo', 'sono in ritardo');
        }
        if ($t['stale'] > 0) {
            $parts[] = F::plural($t['stale'], 'e\' ferma', 'sono ferme');
        }
        $sentence = F::plural($t['active'], 'commessa aperta', 'commesse aperte') . ($parts !== [] ? ': ' . implode(', ', $parts) : '') . '.';
        if ($t['dueThisWeek'] > 0) {
            $sentence .= ' ' . F::plural($t['dueThisWeek'], 'consegna', 'consegne') . ' entro una settimana.';
        }

        return $sentence;
    }

    public function followUpSuggestions(): array
    {
        return ['Le mie attivita', 'Cosa aspettiamo dai clienti?', 'Scadenzario'];
    }
}
