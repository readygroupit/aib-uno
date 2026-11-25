<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\WorkOrderType;
use App\Model\WorkOrderTypeStep;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\WorkOrderTypeStepRepository;
use App\View\Component\TypeStepsComponent;
use App\Work\WorkOrderOverviewService;

final class EditWorkOrderTypeTool extends AbstractEditEntityTool
{
    private WorkOrderTypeStepRepository $steps;
    private TypeStepsComponent $stepsComponent;
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->steps = $container->get(WorkOrderTypeStepRepository::class);
        $this->stepsComponent = $container->get(TypeStepsComponent::class);
    }

    protected function repositoryClass(): string
    {
        return WorkOrderTypeRepository::class;
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_types';
    }

    protected function entityTag(): string
    {
        return 'work_order_type';
    }

    protected function baseUrl(): string
    {
        return '/tipi-commessa';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo tipo di commessa' : 'Tipo di commessa';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Nome e durata';
    }

    protected function searchColumns(): array
    {
        return ['name'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'name', 'label' => 'Tipo']];
    }

    /**
     * Un processo collegato a un servizio si vede (e si modifica) dalla
     * pagina del servizio: li' sta tutto, dati di vendita e processo.
     */
    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        if ($model instanceof WorkOrderType && $model->serviceId !== null && $fieldErrors === []
            && in_array('services', \App\Package\PackageCatalogService::installedNames(), true)) {
            $service = $this->container->get(\App\Repository\ServiceRepository::class)->find((int) $model->serviceId);
            if ($service !== null) {
                return $this->container->get(EditServiceTool::class)->servicePage($service, $message, $messageType, [], 'processo');
            }
        }

        return parent::buildPage($model, $message, $messageType, $fieldErrors);
    }

    /**
     * Le mini guide delle quattro parti del processo (icona "i" accanto al
     * titolo): cosa sono e come si usano, per chi lo prepara la prima volta.
     */
    private const GUIDES = [
        'roles' => [
            'text' => "I ruoli di chi lavora a un progetto di questo tipo (es. Coordinatore, Grafico). Le fasi si assegnano a un ruolo, non a una persona: in ogni progetto si decide chi fa quel ruolo.",
            'steps' => ['Aggiungi un ruolo con "+ Ruolo".', 'Indica chi lo fa di solito: nei progetti nuovi e\' gia\' messo, si puo\' cambiare.', 'Se un ruolo non serve sempre, segnalo come facoltativo.'],
            'tip' => 'Chi ha un ruolo riceve l\'avviso quando tocca a lui.',
        ],
        'milestones' => [
            'text' => "Le date importanti del progetto (briefing, presentazione al cliente...). Non hanno una durata standard: si fissano a mano in ogni progetto.",
            'steps' => ['Aggiungi una data con "+ Data".', 'Scegli il ruolo che la fissa.', 'Se al cliente deve partire un\'email quando si mette la data, scrivi oggetto e testo: prima dell\'invio si vede e si puo\' modificare.'],
        ],
        'steps' => [
            'text' => "Le cose da fare, in ordine. In ogni progetto nuovo diventano attivita' di chi ha il ruolo, e quando una e' fatta parte la successiva.",
            'steps' => ['Aggiungi una fase con "+ Fase" e dalle un ruolo.', 'Scegli il tipo: lavoro, approvazione, attesa del cliente, data chiave o briefing.', 'Raggruppale per fase del processo (es. Vendita, Briefing, Bozze): qui si vedono gia\' divise.'],
            'tip' => 'Un\'approvazione rifiutata puo\' tornare a una fase precedente; una fase "si ripete" serve per i giri di modifiche.',
        ],
        'deliverables' => [
            'text' => "Cosa riceve il cliente alla fine (logo, palette, font...). \"Sempre\" e' compreso in ogni progetto, \"se venduto\" solo se e' nel preventivo.",
            'steps' => ['Aggiungi una voce con "+ Voce".', 'Indica se e\' sempre compresa.'],
        ],
    ];

    /** Cosa e' ogni parte del processo: una riga in cima alla parte aperta. */
    private const INTROS = [
        'roles' => 'Chi lavora ai progetti di questo servizio e chi lo fa di solito.',
        'milestones' => 'Le date da fissare in ogni progetto; alcune mandano un\'email al cliente.',
        'steps' => 'Le cose da fare, in ordine: in ogni progetto nuovo diventano attivita\' di chi ha il ruolo.',
        'deliverables' => 'Cosa riceve il cliente alla fine del progetto.',
    ];

    /** Il processo per la scheda del servizio: la linea con ruoli, date, fasi e consegne. */
    public function processComponents(WorkOrderType $type): array
    {
        $detail = $this->detailComponents($type);
        $detail[0]['embedded'] = true;

        return $detail;
    }

    /**
     * Il resto del processo (cartelle sul Drive, durata, stato) per la
     * scheda "Il servizio". Nome e descrizione non si ripetono: sono quelli
     * del servizio (ServicesController::afterUpdate li tiene allineati);
     * restano nel form come campi nascosti.
     */
    public function processSettingsForm(WorkOrderType $type): array
    {
        $form = $this->buildForm($type);
        $hidden = ['name', 'description', 'service_id'];
        $titles = [
            'folder_template' => ['Cartelle sul Drive', 'Nascono nella cartella di ogni progetto nuovo.', "Scrivi una cartella per riga, nell'ordine in cui vuoi vederle (es. 01 Preventivo, 02 Brief, 03 Bozze). Quando nasce un progetto di questo servizio, il gestionale le crea sul Drive dentro la cartella del progetto."],
            'default_days' => ['Durata e stato', 'Quanto dura di solito un progetto.', "La durata tipica serve all'assistente per capire se un progetto e' in ritardo; le date vere si fissano nel progetto. \"Non in uso\" lo toglie dai progetti nuovi senza cancellarlo."],
        ];
        foreach ($form['sections'] as $si => $section) {
            foreach ($section['boxes'] as $bi => $box) {
                foreach ($box['fields'] as $field) {
                    if (in_array($field['key'], $hidden, true)) {
                        $form['hiddenFields'][] = ['key' => $field['key'], 'value' => is_scalar($field['value'] ?? null) ? (string) $field['value'] : ''];
                    }
                }
                $box['fields'] = array_values(array_filter($box['fields'], static fn (array $f) => !in_array($f['key'], $hidden, true)));
                foreach ($titles as $key => [$title, $description, $guide]) {
                    if (in_array($key, array_column($box['fields'], 'key'), true)) {
                        $box['title'] = $title;
                        $box['description'] = $description;
                        $box['guide'] = $guide;
                    }
                }
                $form['sections'][$si]['boxes'][$bi] = $box;
            }
            $form['sections'][$si]['boxes'] = array_values(array_filter($form['sections'][$si]['boxes'], static fn (array $b) => $b['fields'] !== []));
        }
        $form['title'] = 'Processo di lavoro: cartelle e durata';
        $form['collapsed'] = false;
        $form['secondaryActions'] = [];

        return $form;
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof WorkOrderType) {
            return [];
        }

        $steps = $this->steps->findByType((int) $model->id);
        $total = array_sum(array_map(static fn (WorkOrderTypeStep $s) => (int) $s->estimatedMinutes, $steps));
        $container = $this->container;
        $roles = $container->get(\App\Repository\WorkOrderTypeRoleRepository::class)->findByWorkOrderType((int) $model->id);
        $roleNames = [];
        foreach ($roles as $role) {
            $roleNames[(int) $role->id] = (string) $role->label;
        }
        $milestones = $container->get(\App\Repository\WorkOrderTypeMilestoneRepository::class)->findByWorkOrderType((int) $model->id);
        $deliverables = $container->get(\App\Repository\WorkOrderTypeDeliverableRepository::class)->findByWorkOrderType((int) $model->id);
        $users = [];
        foreach ($container->get(\App\Repository\UserRepository::class)->findAll() as $user) {
            $users[(int) $user->id] = trim("{$user->firstName} {$user->lastName}") ?: (string) $user->username;
        }
        $service = $model->serviceId !== null && in_array('services', \App\Package\PackageCatalogService::installedNames(), true)
            ? $container->get(\App\Repository\ServiceRepository::class)->find((int) $model->serviceId)
            : null;

        return [$this->stepsComponent->toData([
            'url' => '/tipi-commessa/' . $model->id,
            'name' => (string) $model->name,
            'description' => $model->description,
            'days' => $model->defaultDays,
            'guides' => self::GUIDES,
            'intros' => self::INTROS,
            'total' => $total > 0 ? WorkOrderOverviewService::duration($total) : null,
            'steps' => array_map(static fn (WorkOrderTypeStep $s) => [
                'href' => '/tipi-commessa/fasi/' . $s->id,
                'position' => $s->position,
                'title' => (string) $s->title,
                'day' => $s->offsetDays !== null ? 'giorno ' . $s->offsetDays : null,
                'estimate' => $s->estimatedMinutes ? WorkOrderOverviewService::duration((int) $s->estimatedMinutes) : null,
                'waits' => (int) $s->waitsForClient === 1 || $s->kind === 'attesa del cliente',
                'checklist' => count(array_filter(array_map('trim', explode("\n", (string) $s->checklist)))),
                'phase' => $s->phase,
                'kind' => $s->kind,
                'role' => $s->roleId !== null ? ($roleNames[(int) $s->roleId] ?? null) : null,
                'auto' => $s->completesOn,
                'optional' => (int) $s->isOptional === 1,
                'repeatable' => (int) $s->isRepeatable === 1,
            ], $steps),
            'service' => $service !== null ? [
                'name' => (string) $service->name,
                'href' => '/servizi/' . $service->id,
                'price' => (float) $service->price > 0 ? \App\Support\ItalianFormat::euro((float) $service->price) . ($service->billingType === 'ricorrente' ? ' ' . (string) $service->recurrence : '') : null,
                'category' => $service->category,
            ] : null,
            'roles' => array_map(static fn ($r) => [
                'label' => (string) $r->label,
                'who' => $r->defaultUserId !== null ? ($users[(int) $r->defaultUserId] ?? null) : null,
                'fromTeam' => $r->customerTeamRole,
                'optional' => (int) $r->isRequired !== 1,
                'href' => '/tipi-commessa/ruoli/' . $r->id,
            ], $roles),
            'addRoleHref' => '/tipi-commessa/ruoli/nuovo?work_order_type_id=' . $model->id . '&position=' . (count($roles) + 1),
            'milestones' => array_map(static fn ($m) => [
                'label' => (string) $m->label,
                'by' => $m->setByRoleId !== null ? ($roleNames[(int) $m->setByRoleId] ?? null) : null,
                'email' => trim((string) $m->clientEmailSubject) !== '',
                'href' => '/tipi-commessa/date/' . $m->id,
            ], $milestones),
            'addMilestoneHref' => '/tipi-commessa/date/nuovo?work_order_type_id=' . $model->id . '&position=' . (count($milestones) + 1),
            'deliverables' => array_map(static fn ($d) => [
                'label' => (string) $d->label,
                'always' => (int) $d->alwaysIncluded === 1,
                'href' => '/tipi-commessa/consegne/' . $d->id,
            ], $deliverables),
            'addDeliverableHref' => '/tipi-commessa/consegne/nuovo?work_order_type_id=' . $model->id . '&position=' . (count($deliverables) + 1),
            'addHref' => '/tipi-commessa/fasi/nuovo?work_order_type_id=' . $model->id . '&position=' . (count($steps) + 1),
            'newOrderHref' => '/commesse/nuovo?work_order_type_id=' . $model->id,
        ])];
    }

    public function name(): string
    {
        return 'edit_work_order_type';
    }

    public function description(): string
    {
        return "Apre un tipo di commessa con le sue fasi a modello.";
    }

    public function requiredPermission(): ?string
    {
        return 'work_orders.edit';
    }
}
