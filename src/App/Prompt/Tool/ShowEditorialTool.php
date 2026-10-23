<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Calendar\CalendarOverviewService;
use App\Core\Container;
use App\Editorial\EditorialOverviewService;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\EditorialBoardComponent;
use App\View\Component\StatBoxComponent;

/** Il piano editoriale della settimana, con cosa manca (sulle due settimane) e cosa aspetta il cliente. */
final class ShowEditorialTool implements PromptToolInterface, PromptConversationalInterface
{
    private EditorialOverviewService $overview;
    private EditorialBoardComponent $board;
    private StatBoxComponent $statBox;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->overview = $container->get(EditorialOverviewService::class);
        $this->board = $container->get(EditorialBoardComponent::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'show_editorial_plan';
    }

    public function description(): string
    {
        return "Piano editoriale social (Instagram, Facebook, TikTok, LinkedIn) di due settimane per cliente: contenuti in uscita, "
            . "da approvare, da modificare, buchi rispetto al contratto. 'week' (AAAA-MM-GG) per un'altra settimana. "
            . "Usalo per 'piano editoriale', 'post della settimana', 'social', 'cosa esce', 'cosa manca da pubblicare'.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['week' => ['type' => 'string', 'description' => 'Una data della settimana (AAAA-MM-GG)']],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'Piano editoriale';
    }

    public function menuSection(): ?string
    {
        return 'contenuti';
    }

    public function menuCount(): ?string
    {
        $toChange = $this->overview->board(CalendarOverviewService::weekStart(date('Y-m-d')))['totals']['toChange'];

        return $toChange > 0 ? $toChange . ' da modificare' : null;
    }

    public function requiredPermission(): ?string
    {
        return 'editorial.view';
    }

    public function triggers(): array
    {
        return ['piano editoriale', 'social', 'post', 'contenuti social', 'cosa esce questa settimana'];
    }

    public function execute(array $input): array
    {
        $date = isset($input['week']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['week']) === 1 ? (string) $input['week'] : date('Y-m-d');
        $monday = CalendarOverviewService::weekStart($date);
        $current = CalendarOverviewService::weekStart(date('Y-m-d'));
        $board = $this->overview->board($monday);
        $t = $board['totals'];
        $end = date('Y-m-d', strtotime("{$monday} +6 days"));
        $missing = array_sum(array_column($board['gaps'], 'missing'));

        $stats = [
            $this->statBox->toData([
                'label' => 'In uscita questa settimana',
                'value' => (string) $t['thisWeek'],
                'comparison' => $missing > 0 ? F::plural($missing, 'contenuto manca', 'contenuti mancano') . ' rispetto ai contratti' : 'in linea con i contratti',
                'feature' => true,
                'badge' => 'Prossimi 7 giorni',
            ]),
            $this->statBox->toData([
                'label' => 'Da preparare',
                'value' => (string) $t['toPrepare'],
                'comparison' => 'idee e lavorazioni in uscita entro 7 giorni',
                'dotColor' => $t['toPrepare'] > 0 ? 'brass' : 'moss',
            ]),
            $this->statBox->toData([
                'label' => 'In approvazione',
                'value' => (string) ($t['toReview'] + $t['toApprove'] + $t['toChange']),
                'comparison' => implode(', ', array_filter([
                    $t['toReview'] > 0 ? $t['toReview'] . ' da noi' : null,
                    $t['toApprove'] > 0 ? $t['toApprove'] . ' dal cliente' : null,
                    $t['toChange'] > 0 ? $t['toChange'] . ' da modificare' : null,
                ])) ?: 'niente in attesa',
                'dotColor' => $t['toChange'] > 0 ? 'danger' : ($t['toReview'] > 0 ? 'brass' : 'moss'),
            ]),
        ];

        return [$this->board->toData([
            'url' => '/piano-editoriale' . ($monday !== $current ? '?settimana=' . $monday : ''),
            'period' => F::shortDate($monday, date('Y-m-d')) . ' - ' . F::shortDate($end, date('Y-m-d')),
            'prevHref' => '/piano-editoriale?settimana=' . date('Y-m-d', strtotime("{$monday} -7 days")),
            'nextHref' => '/piano-editoriale?settimana=' . date('Y-m-d', strtotime("{$monday} +7 days")),
            'todayHref' => $monday !== $current ? '/piano-editoriale' : null,
            'stats' => $stats,
            'days' => $board['days'],
            'rows' => $board['rows'],
            'gaps' => $board['gaps'],
            // La coda della revisione interna la vede (e la approva) solo chi puo' approvare.
            'review' => $this->auth->hasPermission('editorial.approve') ? $board['review'] : [],
            'createHref' => '/piano-editoriale/nuovo',
            'quotasHref' => '/piano-editoriale/contratti',
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $board = $this->overview->board(CalendarOverviewService::weekStart(date('Y-m-d')));
        $t = $board['totals'];
        $parts = [F::plural($t['thisWeek'], 'contenuto esce', 'contenuti escono') . ' nei prossimi 7 giorni'];
        if ($t['toChange'] > 0) {
            $parts[] = F::plural($t['toChange'], 'e\' da modificare su richiesta del cliente', 'sono da modificare su richiesta dei clienti');
        }
        if ($t['toApprove'] > 0) {
            $parts[] = F::plural($t['toApprove'], 'aspetta', 'aspettano') . " l'approvazione";
        }
        if ($board['gaps'] !== []) {
            $parts[] = 'mancano contenuti per ' . F::plural(count($board['gaps']), 'contratto', 'contratti');
        }

        return ucfirst(implode(', ', $parts)) . '.';
    }

    public function followUpSuggestions(): array
    {
        return ['Le mie attivita', 'Calendario', 'Clienti'];
    }
}
