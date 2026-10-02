<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\MessageTemplateRepository;

final class EditMessageTemplateTool extends AbstractEditEntityTool
{
    protected function repositoryClass(): string
    {
        return MessageTemplateRepository::class;
    }

    protected function packageName(): string
    {
        return 'message_templates';
    }

    protected function entityTag(): string
    {
        return 'message_template';
    }

    protected function baseUrl(): string
    {
        return '/modelli-messaggio';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo modello' : 'Modifica modello';
    }

    protected function searchColumns(): array
    {
        return ['name', 'code', 'body'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'code', 'label' => 'Codice'],
            ['key' => 'channel', 'label' => 'Canale'],
        ];
    }

    public function name(): string
    {
        return 'edit_message_template';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un modello di messaggio, cercato per id o per nome/codice/testo. "
            . "Usalo quando l'utente chiede di aprire o modificare uno specifico modello di messaggio.";
    }

    public function requiredPermission(): ?string
    {
        return 'message_templates.edit';
    }
}
