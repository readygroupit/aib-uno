<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditRefundTool;
use App\Prompt\Tool\ListRefundsTool;
use App\Repository\RefundRepository;

final class RefundsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return RefundRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditRefundTool::class;
    }

    protected function listToolClass(): string
    {
        return ListRefundsTool::class;
    }

    protected function packageName(): string
    {
        return 'refunds';
    }

    protected function listPageTitle(): string
    {
        return 'Rimborsi';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo rimborso';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica rimborso';
    }

    protected function defaultsOnCreate(): array
    {
        return ['payment_status' => 'non pagato'];
    }

    protected function createdMessage(): string
    {
        return 'Rimborso creato.';
    }
}
