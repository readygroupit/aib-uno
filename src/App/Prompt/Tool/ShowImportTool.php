<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Prompt\PromptToolInterface;
use App\View\Component\SheetImportComponent;

/** Importazione dei dati dal foglio di lavoro (vedi App\Import\SheetImporter). */
final class ShowImportTool implements PromptToolInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function name(): string
    {
        return 'show_import';
    }

    public function description(): string
    {
        return "Apre l'importazione dei dati dal foglio Google collegato o da un file Excel (contatti, pratiche, "
            . "rimborsi, documenti). Usalo quando l'utente vuole importare, caricare o sincronizzare i dati del foglio.";
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function menuLabel(): ?string
    {
        return 'Importa dati';
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
        return ['importa', 'importazione', 'importa dati', 'importa foglio', 'importa dal foglio'];
    }

    public function execute(array $input): array
    {
        return [$this->container->get(SheetImportComponent::class)->toData([])];
    }
}
