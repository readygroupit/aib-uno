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
        return ['menu', 'mostra menu', 'cosa puoi fare', 'cosa sai fare'];
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
                'label' => $label,
                'description' => $tool->description(),
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
}
