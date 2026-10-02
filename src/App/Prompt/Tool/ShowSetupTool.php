<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\View\Component\SetupChecklistComponent;

final class ShowSetupTool implements PromptToolInterface
{
    private SetupChecklistComponent $checklist;

    public function __construct(Container $container)
    {
        $this->checklist = $container->get(SetupChecklistComponent::class);
    }

    public function name(): string
    {
        return 'show_setup';
    }

    public function description(): string
    {
        return "Mostra la configurazione del sistema: cosa manca ancora da collegare o preparare (Jotform, "
            . "Google Sheets, modelli di messaggio, WhatsApp...) con la percentuale di completamento. "
            . "Usalo quando l'utente chiede cosa manca, di configurare o collegare un servizio esterno.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Configurazione';
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
        return 'users.manage';
    }

    public function triggers(): array
    {
        return ['configurazione', 'configura', 'setup', 'cosa manca', 'collegamenti', 'connettori'];
    }

    public function execute(array $input): array
    {
        return [$this->checklist->toData([])];
    }
}
