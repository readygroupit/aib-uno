<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\View\Component\AgentsBoardComponent;

final class ShowAgentsTool implements PromptToolInterface
{
    private AgentsBoardComponent $board;

    public function __construct(Container $container)
    {
        $this->board = $container->get(AgentsBoardComponent::class);
    }

    public function name(): string
    {
        return 'show_agents';
    }

    public function description(): string
    {
        return "Mostra gli agenti AI (colleghi digitali): cosa fa ciascuno, come e' programmato e quanta autonomia ha. "
            . "Usalo quando l'utente chiede degli agenti, degli assistenti o di programmarli.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Agenti';
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
        return ['agenti', 'i miei agenti', 'assistenti', 'colleghi digitali', 'programma agenti'];
    }

    public function execute(array $input): array
    {
        return [$this->board->toData([])];
    }
}
