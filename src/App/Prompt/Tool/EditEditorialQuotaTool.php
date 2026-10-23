<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\EditorialQuotaRepository;

final class EditEditorialQuotaTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return EditorialQuotaRepository::class;
    }

    protected function packageName(): string
    {
        return 'editorial';
    }

    protected function manifestEntityKey(): string
    {
        return 'editorial_quotas';
    }

    protected function entityTag(): string
    {
        return 'editorial_quota';
    }

    protected function baseUrl(): string
    {
        return '/piano-editoriale/contratti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo contratto social' : 'Contratto social';
    }

    protected function searchColumns(): array
    {
        return ['channel', 'formats'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'channel', 'label' => 'Canale'],
            ['key' => 'perWeek', 'label' => 'A settimana'],
        ];
    }

    public function name(): string
    {
        return 'edit_editorial_quota';
    }

    public function description(): string
    {
        return "Apre quanti contenuti a settimana prevede il contratto social di un cliente su un canale.";
    }

    public function requiredPermission(): ?string
    {
        return 'editorial.edit';
    }
}
