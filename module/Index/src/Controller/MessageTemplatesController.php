<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditMessageTemplateTool;
use App\Prompt\Tool\ListMessageTemplatesTool;
use App\Repository\MessageTemplateRepository;

final class MessageTemplatesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return MessageTemplateRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditMessageTemplateTool::class;
    }

    protected function listToolClass(): string
    {
        return ListMessageTemplatesTool::class;
    }

    protected function packageName(): string
    {
        return 'message_templates';
    }

    protected function listPageTitle(): string
    {
        return 'Modelli di messaggio';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo modello';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica modello';
    }

    protected function defaultsOnCreate(): array
    {
        return ['channel' => 'email'];
    }

    protected function createdMessage(): string
    {
        return 'Modello creato.';
    }
}
