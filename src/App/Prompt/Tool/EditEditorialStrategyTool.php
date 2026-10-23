<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\EditorialStrategyRepository;

/** La strategia social di un cliente: da qui parte il piano del mese (anche quello proposto dall'IA). */
final class EditEditorialStrategyTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return EditorialStrategyRepository::class;
    }

    protected function packageName(): string
    {
        return 'editorial';
    }

    protected function manifestEntityKey(): string
    {
        return 'editorial_strategies';
    }

    protected function entityTag(): string
    {
        return 'editorial_strategy';
    }

    protected function baseUrl(): string
    {
        return '/piano-editoriale/strategie';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Strategia social del cliente' : 'Strategia social';
    }

    protected function searchColumns(): array
    {
        return ['objectives', 'pillars'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'tone', 'label' => 'Tono'], ['key' => 'objectives', 'label' => 'Obiettivi']];
    }

    public function name(): string
    {
        return 'edit_editorial_strategy';
    }

    public function description(): string
    {
        return "Apre la strategia social di un cliente (obiettivi, pubblico, tono, rubriche): l'IA la usa per proporre il piano editoriale.";
    }

    public function requiredPermission(): ?string
    {
        return 'editorial.edit';
    }
}
