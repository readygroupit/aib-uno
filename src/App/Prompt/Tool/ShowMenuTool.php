<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\Prompt\PromptToolRegistry;
use App\Service\AuthService;
use App\Support\Sections;
use App\View\Component\MenuGridComponent;

/**
 * Il menu si autoaggiorna: elenca ogni capacita' registrata che ha una
 * menuLabel() non nulla ED e' consentita all'utente corrente, invece di
 * una lista scritta a mano da tenere sincronizzata con PromptToolRegistry.
 * La tassonomia delle sezioni e' in App\Support\Sections, condivisa con
 * ListPackagesTool - non duplicata qui.
 */
final class ShowMenuTool implements PromptToolInterface
{
    /** Le voci di tutti i giorni, in testa alla loro sezione; le altre seguono nell'ordine di registrazione. */
    private const FIRST = [
        'show_my_day', 'show_team', 'show_checkin', 'list_tasks', 'list_work_orders', 'show_calendar',
        'show_editorial_plan', 'search_media',
        'list_customers', 'show_client_requests', 'show_client_health',
        'show_quotes', 'show_due_schedule', 'list_invoices', 'show_expenses',
        'show_setup', 'list_users', 'show_profiles',
    ];

    private PromptToolRegistry $registry;
    private MenuGridComponent $menuGrid;
    private AuthService $auth;

    public function __construct(Container $container)
    {
        $this->registry = $container->get(PromptToolRegistry::class);
        $this->menuGrid = $container->get(MenuGridComponent::class);
        $this->auth = $container->get(AuthService::class);
    }

    public function name(): string
    {
        return 'show_menu';
    }

    public function description(): string
    {
        return "Mostra il menu con le funzioni disponibili, a griglia di box.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function menuSection(): ?string
    {
        return null;
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return null;
    }

    public function triggers(): array
    {
        return ['menu', 'mostra menu'];
    }

    public function execute(array $input): array
    {
        $items = [];
        $sectionCounts = [];
        foreach ($this->registry->all() as $tool) {
            $label = $tool->menuLabel();
            $permission = $tool->requiredPermission();
            if ($label === null || ($permission !== null && !$this->auth->hasPermission($permission))) {
                continue;
            }

            $sectionKey = $tool->menuSection();

            $items[] = [
                'name' => $tool->name(),
                'label' => $label,
                'description' => self::shortDescription($tool->description()),
                'prompt' => $label,
                'count' => $tool->menuCount(),
                'section' => $sectionKey,
                'sectionLabel' => Sections::label($sectionKey),
                'sectionColor' => Sections::color($sectionKey),
            ];

            if ($sectionKey !== null) {
                $sectionCounts[$sectionKey] = ($sectionCounts[$sectionKey] ?? 0) + 1;
            }
        }

        // Raggruppate per sezione, nell'ordine di Sections::ALL; dentro la
        // sezione l'ordine di registrazione (le voci piu' usate prima).
        $order = array_flip(array_keys(Sections::ALL));
        $first = array_flip(self::FIRST);
        $position = array_flip(array_keys($items));
        $key = static fn (int $i) => [$order[$items[$i]['section']] ?? 99, $first[$items[$i]['name']] ?? 99, $position[$i]];
        uksort($items, static fn ($a, $b) => $key($a) <=> $key($b));
        $items = array_values($items);

        // Stesso ordine di Sections::ALL - e solo le sezioni che hanno
        // almeno una voce DAVVERO visibile a questo utente (rispetta lo
        // stesso filtro permessi del ciclo qui sopra, una sezione senza
        // voci sbloccate non deve comparire come filtro vuoto).
        $sections = [];
        foreach (Sections::ALL as $key => $meta) {
            if (isset($sectionCounts[$key])) {
                $sections[] = [
                    'key' => $key,
                    'label' => $meta['label'],
                    'color' => $meta['color'],
                    'count' => $sectionCounts[$key],
                ];
            }
        }

        return [$this->menuGrid->toData(['title' => 'Menu', 'items' => $items, 'sections' => $sections])];
    }

    /**
     * La descrizione di uno strumento e' scritta per l'IA (quando usarlo,
     * i parametri): nel menu basta la prima frase.
     */
    private static function shortDescription(string $description): string
    {
        $first = preg_split('/(?<=[.!?])\s/', trim($description), 2)[0] ?? $description;

        return mb_strimwidth($first, 0, 120, "\u{2026}");
    }
}
