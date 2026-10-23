<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\ClientRequests\ClientRequestOverviewService;
use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\ClientRequestBoardComponent;
use App\View\Component\StatBoxComponent;

/** Le richieste dei clienti: cosa e' da smistare, cosa e' in corso, gli extra da mettere in conto. */
final class ShowClientRequestsTool implements PromptToolInterface, PromptConversationalInterface
{
    private ClientRequestOverviewService $overview;
    private ClientRequestBoardComponent $board;
    private StatBoxComponent $statBox;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(ClientRequestOverviewService::class);
        $this->board = $container->get(ClientRequestBoardComponent::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'show_client_requests';
    }

    public function description(): string
    {
        return "Richieste dei clienti arrivate da telefono, WhatsApp, email: da smistare, in corso, lavori extra da mettere in conto. "
            . "Usalo per 'richieste', 'cosa hanno chiesto i clienti', 'richieste da smistare', 'lavori extra'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Richieste dei clienti';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function menuCount(): ?string
    {
        $t = $this->overview->board($this->auth->currentUserId())['totals'];

        return $t['toAssign'] > 0 ? $t['toAssign'] . ' da smistare' : ($t['inProgress'] > 0 ? (string) $t['inProgress'] : null);
    }

    public function requiredPermission(): ?string
    {
        return 'client_requests.view';
    }

    public function triggers(): array
    {
        return ['richieste', 'richieste dei clienti', 'lavori extra', 'cosa hanno chiesto'];
    }

    public function execute(array $input): array
    {
        $board = $this->overview->board($this->auth->currentUserId());
        $t = $board['totals'];

        return [$this->board->toData([
            'stats' => [
                $this->statBox->toData([
                    'label' => 'Da fare',
                    'value' => (string) ($t['toAssign'] + $t['inProgress']),
                    'comparison' => implode(', ', array_filter([
                        $t['toAssign'] > 0 ? $t['toAssign'] . ' da smistare' : null,
                        $t['mine'] > 0 ? $t['mine'] . ' tue' : null,
                        $t['late'] > 0 ? $t['late'] . ' in ritardo' : null,
                    ])) ?: 'nessuna richiesta aperta',
                    'feature' => true,
                    'badge' => 'Adesso',
                ]),
                $this->statBox->toData([
                    'label' => 'Extra da mettere in conto',
                    'value' => F::euro($t['toBillAmount']),
                    'comparison' => F::plural($t['toBill'], 'lavoro fatto', 'lavori fatti') . ', non ancora nello scadenzario',
                    'dotColor' => $t['toBill'] > 0 ? 'brass' : 'moss',
                ]),
                $this->statBox->toData([
                    'label' => 'Richieste in 30 giorni',
                    'value' => (string) $t['lastMonth'],
                    'comparison' => $board['top'] !== [] ? 'la maggior parte da ' . $board['top'][0]['name'] : 'nessuna',
                    'dotColor' => 'moss',
                ]),
            ],
            'groups' => $board['groups'],
            'done' => $board['done'],
            'top' => $board['top'],
            'createHref' => $this->auth->hasPermission('client_requests.create') ? '/richieste/nuovo' : null,
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $t = $this->overview->board($this->auth->currentUserId())['totals'];

        return ucfirst(implode(', ', array_filter([
            F::plural($t['toAssign'] + $t['inProgress'], 'richiesta aperta', 'richieste aperte'),
            $t['toAssign'] > 0 ? $t['toAssign'] . ' da smistare' : null,
            $t['toBill'] > 0 ? F::euro($t['toBillAmount']) . ' di extra da mettere in conto' : null,
        ]))) . '.';
    }

    public function followUpSuggestions(): array
    {
        return ['Nuova richiesta', 'La mia giornata', 'Clienti'];
    }
}
