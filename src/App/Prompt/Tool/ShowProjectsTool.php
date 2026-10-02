<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\View\Component\ProjectsBoardComponent;

final class ShowProjectsTool implements PromptToolInterface
{
    private ProjectsBoardComponent $board;

    public function __construct(Container $container)
    {
        $this->board = $container->get(ProjectsBoardComponent::class);
    }

    public function name(): string
    {
        return 'show_projects';
    }

    public function description(): string
    {
        return "Mostra i progetti generati e il modulo per crearne uno nuovo (cartella, database, pacchetti, dati demo). "
            . "Usalo quando l'utente chiede di creare, generare o vedere i progetti.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Progetti';
    }

    public function menuSection(): ?string
    {
        return 'sistema';
    }

    public function menuCount(): ?string
    {
        return null;
    }

    public function requiredPermission(): ?string
    {
        return 'provisioning.view';
    }

    public function triggers(): array
    {
        return ['progetti', 'nuovo progetto', 'crea progetto', 'genera progetto'];
    }

    public function execute(array $input): array
    {
        return [$this->board->toData([])];
    }
}
