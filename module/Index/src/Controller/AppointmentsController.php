<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Prompt\Tool\EditAppointmentTool;
use App\Prompt\Tool\ListAppointmentsTool;
use App\Repository\AppointmentRepository;

final class AppointmentsController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return AppointmentRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditAppointmentTool::class;
    }

    protected function listToolClass(): string
    {
        return ListAppointmentsTool::class;
    }

    protected function packageName(): string
    {
        return 'appointments';
    }

    protected function listPageTitle(): string
    {
        return 'Appuntamenti';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo appuntamento';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica appuntamento';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'da confermare'];
    }

    protected function createdMessage(): string
    {
        return 'Appuntamento creato.';
    }
}
