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

        return [$this->profile->toData([
            'url' => '/clienti/' . $model->id,
            'customer' => $data['row'] + [
                'email' => $model->email,
                'phone' => $model->mobilePhone ?: $model->phone,
                'website' => $model->website,
                'since' => $model->customerSince !== null ? \App\Support\ItalianFormat::longDate($model->customerSince) : null,
                'referralPercent' => $model->referralPercent,
                'referralTerms' => $model->referralTerms,
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
