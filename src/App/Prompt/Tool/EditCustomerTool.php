<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Billing\AccountOverviewService;
use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\Customer;
use App\Repository\CustomerRepository;
use App\View\Component\CustomerProfileComponent;

/**
 * Primo vero utilizzo di AbstractEditEntityTool: solo i dati specifici
 * di 'customers' (colonne di ricerca, testi, colonne della tabella di
 * scelta multipla) - ricerca/apertura/form/larghezze campi sono
 * ereditati.
 */
final class EditCustomerTool extends AbstractEditEntityTool
{
    /** Pulsante della scheda => permesso che serve (lo stesso che chiede la pagina che apre). */
    private const BUTTON_PERMISSIONS = [
        'newService' => 'customer_services.create',
        'renewService' => 'customer_services.edit',
        'newContact' => 'customers.create',
        'newTeamMember' => 'customers.create',
        'newRequest' => 'client_requests.create',
        'portal' => 'customers.edit',
        'newWorkOrder' => 'work_orders.create',
        'newAppointment' => 'appointments.create',
        'newInstallment' => 'installments.create',
        'logTouchpoint' => 'client_health.create',
    ];

    private AccountOverviewService $overview;
    private CustomerProfileComponent $profile;
    private \App\Work\WorkOrderOverviewService $workOrders;

    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
        $this->overview = $container->get(AccountOverviewService::class);
        $this->profile = $container->get(CustomerProfileComponent::class);
        $this->workOrders = $container->get(\App\Work\WorkOrderOverviewService::class);
    }

    /** Prima la situazione del cliente, poi i suoi dati (form chiuso). */
    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof Customer) {
            return [];
        }

        $data = $this->overview->customerProfile($model);
        $billing = $this->overview->hasBilling();
        // I pulsanti si vedono solo a chi puo' usarli (prima davano "Accesso negato").
        $can = array_map(fn (string $permission) => $this->auth->hasPermission($permission), self::BUTTON_PERMISSIONS);

        $components = [$this->profile->toData([
            'url' => '/clienti/' . $model->id,
            'customer' => $data['row'] + [
                'email' => $model->email,
                'phone' => $model->mobilePhone ?: $model->phone,
                'website' => $model->website,
                'since' => $model->customerSince !== null ? \App\Support\ItalianFormat::longDate($model->customerSince) : null,
                'referralPercent' => $model->referralPercent,
                'referralTerms' => $model->referralTerms,
                // Com'e' il cliente, in testa alla scheda.
                'qualification' => array_values(array_filter([
                    // "cliente" lo dice gia' il tipo di rapporto: si mostra solo potenziale, in trattativa, ex cliente.
                    $model->commercialStage && $model->commercialStage !== 'cliente' ? ['label' => (string) $model->commercialStage, 'tone' => $model->commercialStage === 'in trattativa' ? 'info' : 'neutral'] : null,
                    $model->temperature ? ['label' => (string) $model->temperature, 'tone' => match ((string) $model->temperature) { 'caldo' => 'danger', 'tiepido' => 'warning', default => 'info' }, 'icon' => 'temperature-half'] : null,
                    $model->temperament && $model->temperament !== 'normale' ? ['label' => 'da seguire: ' . $model->temperament, 'tone' => in_array($model->temperament, ['esigente', 'difficile'], true) ? 'warning' : 'success'] : null,
                    $model->nextContactDate ? ['label' => 'risentirlo ' . \App\Support\ItalianFormat::shortDate((string) $model->nextContactDate, date('Y-m-d')), 'tone' => (string) $model->nextContactDate <= date('Y-m-d') ? 'danger' : 'neutral', 'icon' => 'calendar'] : null,
                ])),
            ],
            'services' => $data['services'],
            'installments' => $data['installments'],
            'contacts' => $data['contacts'],
            'team' => $data['team'],
            'referrer' => $data['referrer'],
            'brought' => $data['brought'],
            'showBilling' => $billing,
            'workOrders' => $this->workOrders->hasWorkOrders() ? $this->workOrders->forCustomer((int) $model->id) : null,
            'appointments' => in_array('appointments', \App\Package\PackageCatalogService::installedNames(), true)
                ? $this->container->get(\App\Calendar\CalendarOverviewService::class)->shortList($this->container->get(\App\Repository\AppointmentRepository::class)->findUpcomingForCustomer((int) $model->id))
                : null,
            'newAppointmentHref' => '/appuntamenti/nuovo?customer_id=' . $model->id,
            'newWorkOrderHref' => $this->workOrders->hasWorkOrders() ? '/commesse/nuovo?customer_id=' . $model->id : null,
            'health' => \App\ClientHealth\HealthScore::isAvailable() ? $this->container->get(\App\ClientHealth\HealthOverviewService::class)->forCustomer((int) $model->id) : null,
            'can' => $can,
            'actions' => array_values(array_filter([
                $billing && $can['newService'] ? ['label' => 'Nuovo servizio', 'href' => '/servizi-clienti/nuovo?customer_id=' . $model->id, 'variant' => 'primary'] : null,
                $can['newContact'] ? ['label' => 'Aggiungi referente', 'href' => '/clienti/referenti/nuovo?customer_id=' . $model->id, 'variant' => 'secondary'] : null,
                in_array('client_requests', \App\Package\PackageCatalogService::installedNames(), true) && $can['newRequest'] ? ['label' => 'Segna una richiesta', 'href' => '/richieste/nuovo?customer_id=' . $model->id, 'variant' => 'secondary'] : null,
                $billing && $can['portal'] ? ['label' => 'Area cliente', 'action' => '/clienti/' . $model->id . '/area-cliente', 'variant' => 'ghost', 'icon' => 'link'] : null,
            ])),
        ])];
        if (\App\Documents\DocumentService::isInstalled() && $this->auth->hasPermission('documents.view')
            && ($documents = $this->container->get(\App\Documents\DocumentService::class)->summaryFor(null, (int) $model->id)) !== null) {
            $components[] = $documents;
        }
        // Quello che sappiamo del cliente (profilo, roadmap, diario): lo usa anche l'assistente.
        if (\App\Knowledge\KnowledgeService::isInstalled() && $this->auth->hasPermission('client_knowledge.view')) {
            $components[] = $this->container->get(\App\Knowledge\KnowledgeService::class)->forCustomer((int) $model->id);
        }

        return $components;
    }

    /**
     * Il cliente: in testa chi e' e le azioni; sotto le schede "Lavori"
     * (progetti, appuntamenti), "Rapporto" (temperatura, diario e profilo),
     * "Persone" (squadra interna, referenti), "Servizi e pagamenti",
     * "Documenti" e "Dati" (il form). Le schede vuote non ci sono.
     */
    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        if (!$model instanceof Customer || $model->id === null) {
            return parent::buildPage($model, $message, $messageType, $fieldErrors);
        }
        $detail = $this->detailComponents($model);
        $profile = array_shift($detail);
        $profile['guides'] = [
            'workOrders' => "I progetti del cliente, prima quelli in corso o che aspettano. \"Avvia un servizio\" apre un progetto nuovo con il processo del servizio.",
            'appointments' => 'Gli appuntamenti in programma con il cliente.',
            'health' => "Come va il rapporto: si misura dai contatti e dai servizi attivi. Dopo una telefonata o un incontro, \"Com'e' andata\" lo registra.",
            'team' => "Chi segue il cliente in agenzia e per cosa: contenuti e richieste si assegnano da soli a queste persone.",
            'contacts' => "Le persone del cliente con cui parlate. \"decide\" indica chi prende le decisioni.",
            'services' => 'I servizi attivi del cliente e le prossime scadenze.',
            'installments' => 'I pagamenti in programma, dalle rate dei servizi.',
        ];
        $profile['headGuide'] = [
            'title' => 'La scheda del cliente',
            'text' => "In alto chi e' il cliente e le azioni del momento; sotto, il suo lavoro diviso in schede.",
            'steps' => ['Lavori: progetti e appuntamenti.', 'Rapporto: com\'e\' messo il rapporto, il diario e cosa sappiamo di lui.', 'Persone: chi lo segue da noi e i suoi referenti.', 'Dati: anagrafica, fatturazione e pagamento.'],
            'tip' => 'Referenti e persone si aggiungono nel pannello laterale, senza lasciare la scheda.',
        ];
        $part = static fn (array $only) => ['only' => $only] + $profile;
        $byType = static fn (string $type) => array_values(array_filter($detail, static fn (array $c) => ($c['type'] ?? '') === $type));

        $tabs = [];
        $active = is_array($profile['workOrders'] ?? null) ? count(array_filter($profile['workOrders'], static fn (array $w) => !empty($w['active']))) : 0;
        if (($profile['workOrders'] ?? null) !== null || ($profile['appointments'] ?? null) !== null || !empty($profile['recap'])) {
            $tabs[] = ['key' => 'lavori', 'label' => 'Lavori', 'icon' => 'list-check', 'color' => 'teal', 'count' => $active > 0 ? $active : null, 'components' => [$part(['recap', 'workOrders', 'appointments'])]];
        }
        // Com'e' il cliente (a che punto, caldo o freddo, importanza, com'e' da
        // seguire, quando risentirlo): si segna qui, in cima al Rapporto.
        $qualification = $this->formWithOnly($model, ['commercial_stage', 'temperature', 'importance', 'temperament', 'next_contact_date', 'next_contact_note'], "Com'e' il cliente");
        $qualification['reloadAfterSave'] = true;
        $qualification['submitLabel'] = 'Salva';
        $qualification['guide'] = [
            'text' => "Come sta andando con questo cliente. L'assistente lo usa per ricordarti quando risentirlo e con che tono scrivere.",
            'steps' => ['A che punto e\': potenziale, in trattativa, cliente, ex cliente.', 'Quanto e\' caldo: caldo (pronto a comprare), tiepido, freddo.', 'Importanza: chiave (ottimo cliente), abituale, occasionale.', 'Com\'e\' da seguire: facile, normale, esigente, difficile.', 'Risentirlo il: la data in cui l\'assistente te lo ricorda, con il perche\'.'],
        ];
        $relation = [$qualification];
        if (($profile['health'] ?? null) !== null) {
            $relation[] = $part(['health']);
        }
        $relation = [...$relation, ...$byType('customer-knowledge')];
        if ($relation !== []) {
            $tabs[] = ['key' => 'rapporto', 'count' => null, 'label' => 'Rapporto', 'icon' => 'temperature-half', 'color' => 'brass', 'components' => $relation];
        }
        $tabs[] = ['key' => 'persone', 'label' => 'Persone', 'icon' => 'users', 'color' => 'lilac', 'count' => count($profile['contacts'] ?? []) ?: null, 'components' => [$part(['team', 'contacts', 'brought'])]];
        if (!empty($profile['showBilling'])) {
            $tabs[] = ['key' => 'servizi', 'label' => 'Servizi e pagamenti', 'short' => 'Servizi', 'icon' => 'coins', 'color' => 'moss', 'components' => [$part(['kpi', 'services'])]];
        }
        if (($documents = $byType('document-summary')) !== []) {
            $tabs[] = ['key' => 'documenti', 'label' => 'Documenti', 'short' => 'File', 'icon' => 'folder', 'color' => 'petrol', 'components' => $documents];
        }

        return $this->tabbedPage($model, $part(['head']), $tabs, $message, $messageType, $fieldErrors, ['key' => 'dati', 'label' => 'Dati', 'icon' => 'pen']);
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Dati del cliente';
    }

    protected function repositoryClass(): string
    {
        return CustomerRepository::class;
    }

    protected function packageName(): string
    {
        return 'customers';
    }

    protected function entityTag(): string
    {
        return 'customer';
    }

    protected function baseUrl(): string
    {
        return '/clienti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo cliente' : 'Modifica cliente';
    }

    protected function searchColumns(): array
    {
        return ['first_name', 'last_name', 'company_name', 'email', 'phone', 'vat_number'];
    }

    protected function phoneticColumns(): array
    {
        return ['first_name', 'last_name', 'company_name'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'firstName', 'label' => 'Nome'],
            ['key' => 'lastName', 'label' => 'Cognome'],
            ['key' => 'companyName', 'label' => 'Ragione sociale'],
            ['key' => 'email', 'label' => 'Email'],
        ];
    }

    public function name(): string
    {
        return 'edit_customer';
    }

    public function description(): string
    {
        return "Apre la scheda di modifica di un cliente, cercato per id o per nome/cognome/ragione sociale/email/telefono/partita IVA. "
            . "Usalo quando l'utente chiede di aprire, vedere il dettaglio o modificare uno specifico cliente "
            . "(es. 'modifica il cliente Rossi', 'apri il cliente con partita iva ...').";
    }

    public function requiredPermission(): ?string
    {
        return 'customers.edit';
    }
}
