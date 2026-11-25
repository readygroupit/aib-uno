<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\SeasonalEventRepository;

/** Un periodo forte del calendario dell'assistente (Natale, vendemmia...). */
final class EditSeasonalEventTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return SeasonalEventRepository::class;
    }

    protected function packageName(): string
    {
        return 'assistant';
    }

    protected function manifestEntityKey(): string
    {
        return 'seasonal_events';
    }

    protected function entityTag(): string
    {
        return 'seasonal_event';
    }

    protected function baseUrl(): string
    {
        return '/assistente/stagioni';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo periodo' : 'Periodo forte';
    }

    protected function searchColumns(): array
    {
        return ['name'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'name', 'label' => 'Periodo']];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function name(): string
    {
        return 'edit_seasonal_event';
    }

    public function description(): string
    {
        return "Apre un periodo forte del calendario dell'assistente.";
    }

    public function requiredPermission(): ?string
    {
        return 'assistant.manage';
    }
}
