<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Leaves\LeaveService;
use App\Model\LeaveRequest;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\LeaveRequestRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Support\ItalianFormat as F;
use App\View\Component\SimpleBoardComponent;

/**
 * Ferie e permessi: le proprie richieste; a chi approva, quelle da decidere
 * e chi e' via nei prossimi 30 giorni.
 */
final class ShowLeavesTool implements PromptToolInterface, PromptConversationalInterface
{
    private Container $container;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'show_leaves';
    }

    public function description(): string
    {
        return "Ferie, permessi e ROL: le mie richieste, quelle da approvare, chi e' via nei prossimi giorni. Usalo per 'ferie', 'permessi', 'chi e' in ferie'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Ferie e permessi';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        if (!$this->auth->hasPermission('leaves.approve')) {
            return null;
        }
        $pending = count($this->container->get(LeaveRequestRepository::class)->findPending());

        return $pending > 0 ? $pending . ' da approvare' : null;
    }

    public function requiredPermission(): ?string
    {
        return 'leaves.view';
    }

    public function triggers(): array
    {
        return ['ferie', 'permessi', 'rol', 'chi e in ferie', 'assenze'];
    }

    public function execute(array $input): array
    {
        $repo = $this->container->get(LeaveRequestRepository::class);
        $me = (int) $this->auth->currentUserId();
        $approver = $this->auth->hasPermission('leaves.approve');
        $people = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $people[(int) $user->id] = trim("{$user->firstName} {$user->lastName}");
        }
        $today = date('Y-m-d');
        $row = function (LeaveRequest $l, bool $withName, array $actions = []) use ($people, $today): array {
            $span = $l->endDate !== null && $l->endDate !== $l->startDate
                ? 'dal ' . F::shortDate($l->startDate, $today) . ' al ' . F::shortDate($l->endDate, $today)
                : F::shortDate($l->startDate, $today) . ($l->isPartial() ? " dalle {$l->startTime} alle {$l->endTime}" : '');

            return [
                'href' => '/ferie/' . $l->id,
                'title' => ($withName ? ($people[(int) $l->userId] ?? '') . ' · ' : '') . ucfirst((string) $l->kind) . ' ' . $span,
                'meta' => implode(' · ', array_filter([$l->note, $l->reply ? 'risposta: ' . $l->reply : null])),
                'badge' => ['label' => (string) $l->stage, 'tone' => match ($l->stage) { 'approvata' => 'success', 'rifiutata' => 'danger', 'richiesta' => 'warning', default => 'neutral' }],
                'actions' => $actions,
            ];
        };

        $sections = [];
        if ($approver) {
            $pending = $repo->findPending();
            $sections[] = [
                'title' => 'Da approvare',
                'tone' => $pending !== [] ? 'warning' : 'neutral',
                'empty' => 'Nessuna richiesta da decidere.',
                'rows' => array_map(fn (LeaveRequest $l) => $row($l, true, [
                    ['label' => 'Approva', 'action' => '/ferie/' . $l->id . '/decidi', 'body' => ['esito' => 'si'], 'variant' => 'primary', 'icon' => 'check'],
                    ['label' => 'No', 'action' => '/ferie/' . $l->id . '/decidi', 'body' => ['esito' => 'no'], 'variant' => 'ghost', 'ask' => 'Perche\'? (lo vede chi l\'ha chiesta)', 'placeholder' => 'Es. quella settimana c\'e\' lo shooting di Aurora'],
                ]), $pending),
            ];
            $away = $repo->findApprovedBetween($today, date('Y-m-d', strtotime('+30 days')));
            $sections[] = [
                'title' => 'Chi e\' via nei prossimi 30 giorni',
                'tone' => 'info',
                'empty' => 'Nessuno.',
                'rows' => array_map(fn (LeaveRequest $l) => $row($l, true), $away),
            ];
        }
        $mine = array_slice($repo->findForUser($me), 0, 12);
        $sections[] = [
            'title' => 'Le mie richieste',
            'tone' => 'neutral',
            'empty' => 'Nessuna richiesta.',
            'rows' => array_map(fn (LeaveRequest $l) => $row($l, false), $mine),
        ];

        return [$this->container->get(SimpleBoardComponent::class)->toData([
            'title' => 'Ferie e permessi',
            'url' => '/ferie',
            'createHref' => '/ferie/nuovo',
            'createLabel' => 'Chiedi ferie o permesso',
            'sections' => $sections,
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $repo = $this->container->get(LeaveRequestRepository::class);
        $away = $repo->findApprovedBetween(date('Y-m-d'), date('Y-m-d'));

        return ($away === [] ? 'Oggi ci sono tutti.' : 'Oggi ' . F::plural(count($away), 'persona e\' via', 'persone sono via') . '.')
            . ($this->auth->hasPermission('leaves.approve') && $repo->findPending() !== [] ? ' Ci sono richieste da approvare.' : '');
    }

    public function followUpSuggestions(): array
    {
        return ['La mia giornata', 'Il team oggi'];
    }
}
