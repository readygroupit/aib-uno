<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Billing\AccountOverviewService;
use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\Customer;
use App\Model\CustomerService;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\View\Component\ServiceSummaryComponent;

final class EditCustomerServiceTool extends AbstractEditEntityTool
{
    private AccountOverviewService $overview;
    private CustomerRepository $customers;
    private ServiceSummaryComponent $summary;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->overview = $container->get(AccountOverviewService::class);
        $this->customers = $container->get(CustomerRepository::class);
        $this->summary = $container->get(ServiceSummaryComponent::class);
    }

    protected function repositoryClass(): string
    {
        return CustomerServiceRepository::class;
    }

    protected function packageName(): string
    {
        return 'customer_services';
    }

    protected function entityTag(): string
    {
        return 'customer_service';
    }

    protected function baseUrl(): string
    {
        return '/servizi-clienti';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuovo servizio per un cliente' : 'Modifica servizio';
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Dati del servizio';
    }

    protected function searchColumns(): array
    {
        return ['title'];
    }

    protected function candidateColumns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Servizio'],
            ['key' => 'stage', 'label' => 'Stato'],
            ['key' => 'renewalDate', 'label' => 'Rinnovo'],
        ];
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof CustomerService) {
            return [];
        }

        $customer = $model->customerId !== null ? $this->customers->find((int) $model->customerId) : null;
        $data = $this->overview->serviceDetail($model);

        $actions = [];
        if ($data['service']['canRenew']) {
            $actions[] = [
                'label' => 'Rinnova ora',
                'action' => '/servizi-clienti/' . $model->id . '/rinnova',
                'confirm' => "Rinnovare adesso per un altro periodo? Si aggiungono allo scadenzario gli incassi del nuovo periodo.",
                'variant' => 'primary',
            ];
        }
        $actions[] = [
            'label' => 'Ricrea le scadenze',
            'action' => '/servizi-clienti/' . $model->id . '/ricrea-scadenze',
            'confirm' => "Rifare le scadenze da incassare con il piano attuale? Funziona solo se non e' ancora stato incassato nulla.",
            'variant' => 'secondary',
        ];

        return [$this->summary->toData([
            'url' => '/servizi-clienti/' . $model->id,
            'service' => $data['service'],
            'customer' => $customer instanceof Customer ? ['name' => $customer->displayName(), 'href' => '/clienti/' . $customer->id] : null,
            'installments' => $data['installments'],
            'actions' => $actions,
        ])];
    }

    public function name(): string
    {
        return 'edit_customer_service';
    }

    public function description(): string
    {
        return "Apre un servizio venduto a un cliente (con prezzo, rinnovo e scadenze di pagamento), cercato per id o descrizione.";
    }

    public function requiredPermission(): ?string
    {
        return 'customer_services.edit';
    }
}
