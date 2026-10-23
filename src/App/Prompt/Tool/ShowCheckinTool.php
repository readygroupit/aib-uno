<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Service\AuthService;
use App\View\Component\CheckinComponent;
use App\Worklog\CheckinService;

/** "Cosa hai fatto?": le attivita' da confermare per il pezzo di giornata appena passato. */
final class ShowCheckinTool implements PromptToolInterface, PromptConversationalInterface
{
    private CheckinService $checkins;
    private AuthService $auth;
    private CheckinComponent $component;

    public function __construct(Container $container)
    {
        $this->checkins = $container->get(CheckinService::class);
        $this->auth = $container->get(AuthService::class);
        $this->component = $container->get(CheckinComponent::class);
    }

    public function name(): string
    {
        return 'show_checkin';
    }

    public function description(): string
    {
        return "Segnare cosa si e' fatto e quanto ci si e' messo (il registro attivita'): propone le attivita' del piano da confermare. "
            . "Usalo per 'cosa ho fatto', 'segna il lavoro', 'registra le ore', 'fine giornata'.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Cosa ho fatto';
    }

    public function menuSection(): ?string
    {
        return 'operativita';
    }

    public function menuCount(): ?string
    {
        return $this->checkins->due((int) $this->auth->currentUserId()) !== null ? 'da segnare' : null;
    }

    public function requiredPermission(): ?string
    {
        return 'worklog.view';
    }

    public function triggers(): array
    {
        return ['cosa ho fatto', 'segna il lavoro', 'registra il lavoro', 'registra le ore', 'fine giornata', 'check-in'];
    }

    /** @param array{date?:string, period?:string} $input senza: il momento da segnare, o oggi */
    public function execute(array $input): array
    {
        $userId = (int) $this->auth->currentUserId();
        $due = $this->checkins->due($userId);
        $date = isset($input['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $input['date']) === 1 ? (string) $input['date'] : ($due['date'] ?? date('Y-m-d'));
        $period = in_array($input['period'] ?? null, ['mattina', 'pomeriggio', 'giornata'], true) ? (string) $input['period'] : ($due['period'] ?? 'giornata');

        $label = $due !== null && $due['date'] === $date && $due['period'] === $period
            ? $due['label']
            : ($date === date('Y-m-d') ? 'oggi' : 'quel giorno');

        return [$this->component->toData([
            'url' => '/registro/check-in',
            'title' => "Cosa hai fatto {$label}?",
            'lead' => 'Conferma con un tocco quello che hai fatto e correggi i tempi se serve. Due minuti, e l\'organizer impara quanto durano davvero i lavori.',
            'date' => $date,
            'period' => $period,
            'rows' => $this->checkins->proposals($userId, $date, $period),
            'action' => '/registro/check-in',
            'privacy' => 'Serve a capire quanto durano i lavori e a pianificare meglio, non a controllare le persone.',
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $due = $this->checkins->due((int) $this->auth->currentUserId());

        return ($due !== null ? $due['question'] : "Cosa hai fatto oggi?") . " Ti ho preparato l'elenco: conferma e correggi i tempi.";
    }

    public function followUpSuggestions(): array
    {
        return ['Il mio registro', 'La mia giornata'];
    }
}
