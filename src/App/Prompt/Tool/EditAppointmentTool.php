<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Calendar\CalendarOverviewService;
use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\Appointment;
use App\Repository\AppointmentRepository;
use App\View\Component\AppointmentSummaryComponent;

final class EditAppointmentTool extends AbstractEditEntityTool
{
    private CalendarOverviewService $overview;
    private AppointmentSummaryComponent $summary;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->overview = $container->get(CalendarOverviewService::class);
        $this->summary = $container->get(AppointmentSummaryComponent::class);
    }

    protected function formTitleBelowDetail(): ?string
    {
        return "Dettagli dell'appuntamento";
    }

    /** Collegamento a una commessa e campi scritti dalla sincronizzazione con Google: non si toccano a mano. */
    protected function hiddenFields(): array
    {
        return ['entity_type', 'entity_id'];
    }

    protected function systemManagedFields(): array
    {
        return ['google_event_id', 'google_synced_at', 'source'];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof Appointment) {
            return [];
        }

        $base = '/appuntamenti/' . $model->id;
        $actions = [];
        if ($model->stage === 'da confermare') {
            $actions[] = ['label' => 'Confermato', 'action' => "{$base}/stato?stage=confermato", 'variant' => 'primary', 'icon' => 'check'];
        }
        if (in_array($model->stage, ['da confermare', 'confermato'], true)) {
            if (strtotime((string) $model->startAt) <= time()) {
                $actions[] = ['label' => 'Fatto', 'action' => "{$base}/stato?stage=fatto", 'variant' => 'primary', 'icon' => 'circle-check'];
            }
            $actions[] = ['label' => 'Annulla', 'action' => "{$base}/stato?stage=annullato", 'variant' => 'ghost', 'confirm' => "Annullare l'appuntamento? Sparisce anche dal Google Calendar dei partecipanti."];
        } elseif ($model->stage === 'annullato') {
            $actions[] = ['label' => 'Ripristina', 'action' => "{$base}/stato?stage=confermato", 'variant' => 'secondary'];
        }

        return [$this->summary->toData(['url' => $base, 'actions' => $actions] + $this->overview->detail($model))];
    }

    protected function repositoryClass(): string
    {
        return AppointmentRepository::class;
    }

    protected function packageName(): string
    {
        return 'appointments';
    }

    protected function entityTag(): string
    {
        return 'appointment';
    }

    protected function baseUrl(): string
    {
        return '/appuntamenti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo appuntamento' : 'Appuntamento';
    }

    protected function searchColumns(): array
    {
        return ['title', 'location', 'notes'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titolo'],
            ['key' => 'startAt', 'label' => 'Inizio'],
            ['key' => 'location', 'label' => 'Luogo'],
        ];
    }

    public function name(): string
    {
        return 'edit_appointment';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un appuntamento, cercato per id o per titolo/luogo/note. "
            . "Usalo quando l'utente chiede di aprire o modificare un appuntamento specifico.";
    }

    public function requiredPermission(): ?string
    {
        return 'appointments.edit';
    }
}
