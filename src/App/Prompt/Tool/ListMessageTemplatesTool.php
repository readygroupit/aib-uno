<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Repository\MessageTemplateRepository;

final class ListMessageTemplatesTool extends AbstractListEntityTool
{
    protected function repositoryClass(): string
    {
        return MessageTemplateRepository::class;
    }

    protected function entityTag(): string
    {
        return 'message_template';
    }

    protected function baseUrl(): string
    {
        return '/modelli-messaggio';
    }

    protected function title(): string
    {
        return 'Modelli di messaggio';
    }

    protected function statLabel(): string
    {
        return 'Totale modelli';
    }

    protected function createLabel(): ?string
    {
        return 'Nuovo modello';
    }

    protected function createPermission(): ?string
    {
        return 'message_templates.create';
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Nome'],
            ['key' => 'code', 'label' => 'Codice'],
            ['key' => 'channel', 'label' => 'Canale', 'badge' => true],
            ['key' => 'subject', 'label' => 'Oggetto'],
        ];
    }

    protected function actions(): array
    {
        return [
            ['label' => 'Modifica', 'href' => '/modelli-messaggio/{id}', 'permission' => 'message_templates.edit'],
        ];
    }

    public function name(): string
    {
        return 'list_message_templates';
    }

    public function description(): string
    {
        return "Mostra l'elenco dei modelli di messaggio (testi predefiniti per email e WhatsApp).";
    }

    public function menuLabel(): ?string
    {
        return 'Modelli di messaggio';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function requiredPermission(): ?string
    {
        return 'message_templates.view';
    }

    public function triggers(): array
    {
        return ['modelli di messaggio', 'modelli messaggio', 'modelli', 'template messaggi'];
    }
}
