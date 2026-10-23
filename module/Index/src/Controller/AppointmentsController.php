<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Calendar\CalendarSyncService;
use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\Appointment;
use App\Prompt\Tool\EditAppointmentTool;
use App\Prompt\Tool\ShowCalendarTool;
use App\Repository\AppointmentRepository;
use App\Service\AuthService;

/**
 * Calendario e appuntamenti. Ogni salvataggio va subito anche su Google
 * Calendar, se collegato; se Google non risponde ci riprova il job.
 */
final class AppointmentsController extends AbstractEntityController
{
    /** Durata proposta quando manca la fine, per tipo. */
    private const DEFAULT_MINUTES = ['shooting' => 480, 'riprese' => 240, 'evento' => 240, 'sopralluogo' => 90, 'call' => 30];

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
        return ShowCalendarTool::class;
    }

    protected function packageName(): string
    {
        return 'appointments';
    }

    protected function listPageTitle(): string
    {
        return 'Calendario';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo appuntamento';
    }

    protected function editPageTitle(): string
    {
        return 'Appuntamento';
    }

    protected function defaultsOnCreate(): array
    {
        return [
            'stage' => 'confermato',
            'start_at' => date('Y-m-d', strtotime('+1 day')) . ' 10:00:00',
            'assigned_user_id' => $this->container->get(AuthService::class)->currentUserId(),
            'is_online' => '0',
            'source' => 'uno',
        ];
    }

    protected function createdMessage(): string
    {
        return 'Appuntamento creato.';
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'stage' => 'appointments.edit',
            'sync' => 'appointments.view',
            default => parent::requiredPermissionForAction($action),
        };
    }

    /** ?settimana=AAAA-MM-GG per un'altra settimana, ?vista=elenco per i prossimi giorni. */
    public function indexAction(): ?string
    {
        $components = $this->container->get(ShowCalendarTool::class)->execute([
            'week' => (string) $this->request->get('settimana', ''),
            'view' => (string) $this->request->get('vista', 'settimana'),
        ]);

        return $this->renderPage($components, ['title' => $this->listPageTitle()]);
    }

    protected function crossFieldErrors(array $data): array
    {
        if (!empty($data['start_at']) && !empty($data['end_at']) && $data['end_at'] <= $data['start_at']) {
            return ['end_at' => "La fine dev'essere dopo l'inizio."];
        }

        return [];
    }

    protected function afterCreate(int $id): void
    {
        $appointment = $this->repository()->find($id);
        if ($appointment instanceof Appointment && $appointment->endAt === null) {
            $minutes = self::DEFAULT_MINUTES[$appointment->kind] ?? 60;
            $this->repository()->update($id, ['end_at' => date('Y-m-d H:i:s', strtotime((string) $appointment->startAt) + 60 * $minutes)]);
        }
        $this->pushMessage = $this->container->get(CalendarSyncService::class)->push($id);
    }

    protected function componentsAfterCreate(int $id): array
    {
        $message = 'Appuntamento creato' . ($this->container->get(CalendarSyncService::class)->isEnabled() && $this->pushMessage === null ? ' e messo sul Google Calendar.' : '.');

        return $this->container->get(EditAppointmentTool::class)->buildPage($this->repository()->find($id), $this->pushMessage ?? $message, 'success');
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        return $this->container->get(CalendarSyncService::class)->push($id);
    }

    protected function afterDelete(int $id): void
    {
        $this->container->get(CalendarSyncService::class)->push($id);
    }

    private ?string $pushMessage = null;

    /** Confermato / fatto / annullato con un clic dalla scheda. */
    public function stageAction(): ?string
    {
        $id = (int) $this->param('id');
        $stage = (string) $this->request->get('stage', '');
        $labels = ['confermato' => 'Confermato.', 'fatto' => 'Segnato come fatto.', 'annullato' => 'Annullato: tolto anche dal Google Calendar.'];
        if (!isset($labels[$stage])) {
            $this->json(['ok' => false, 'message' => 'Stato non valido.'], 422);

            return null;
        }

        $this->repository()->update($id, ['stage' => $stage]);
        $warning = $this->container->get(CalendarSyncService::class)->push($id);

        return $this->renderPage(
            $this->container->get(EditAppointmentTool::class)->buildPage($this->repository()->find($id), $warning ?? $labels[$stage], $warning !== null ? 'error' : 'success'),
            ['title' => $this->editPageTitle()]
        );
    }

    /** "Sincronizza ora" dal calendario. */
    public function syncAction(): void
    {
        $result = $this->container->get(CalendarSyncService::class)->syncAll();
        if ($result['error'] !== null) {
            $this->json(['ok' => false, 'message' => $result['error']]);

            return;
        }
        $this->json(['ok' => true, 'message' => "Sincronizzato con Google: {$result['pushed']} inviati, {$result['pulled']} aggiornati da Google."]);
    }
}
