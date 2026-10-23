<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Billing\PaymentPlanService;
use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Model\CustomerService;
use App\Prompt\Tool\EditCustomerServiceTool;
use App\Prompt\Tool\ListCustomerServicesTool;
use App\Repository\CustomerServiceRepository;
use App\Support\ItalianFormat as F;

/**
 * Servizi venduti ai clienti. Alla creazione nasce lo scadenzario dal
 * piano di pagamento (PaymentPlanService); se dopo si cambia il piano o
 * l'importo, le scadenze ancora da incassare si rifanno da sole.
 */
final class CustomerServicesController extends AbstractEntityController
{
    /** Campi che cambiano gli incassi attesi: se cambiano, si rifanno le scadenze aperte. */
    private const PLAN_FIELDS = ['price', 'billing_type', 'recurrence', 'start_date', 'payment_plan', 'deposit_percent', 'balance_date', 'installments_count'];

    protected function repositoryClass(): string
    {
        return CustomerServiceRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCustomerServiceTool::class;
    }

    protected function listToolClass(): string
    {
        return ListCustomerServicesTool::class;
    }

    protected function packageName(): string
    {
        return 'customer_services';
    }

    protected function listPageTitle(): string
    {
        return 'Servizi venduti';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo servizio per un cliente';
    }

    protected function editPageTitle(): string
    {
        return 'Servizio';
    }

    protected function defaultsOnCreate(): array
    {
        return [
            'stage' => 'attivo',
            'billing_type' => 'una tantum',
            'payment_plan' => 'unica soluzione',
            'start_date' => date('Y-m-d'),
            'notice_days' => '21',
            'auto_renew' => '0',
            'payment_method' => 'bonifico',
        ];
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'renew', 'rebuild' => 'customer_services.edit',
            default => parent::requiredPermissionForAction($action),
        };
    }

    protected function afterCreate(int $id): void
    {
        $this->container->get(PaymentPlanService::class)->createInitialPlan($id);
    }

    protected function componentsAfterCreate(int $id): array
    {
        $count = count($this->container->get(\App\Repository\InstallmentRepository::class)->findByService($id));
        $message = $count > 0
            ? 'Servizio creato: ' . F::plural($count, 'scadenza aggiunta', 'scadenze aggiunte') . ' allo scadenzario.'
            : 'Servizio creato.';

        return $this->container->get(EditCustomerServiceTool::class)->buildPage($this->repository()->find($id), $message, 'success');
    }

    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $after = $this->repository()->find($id);
        if (!$before instanceof CustomerService || !$after instanceof CustomerService) {
            return null;
        }

        $changed = false;
        foreach (self::PLAN_FIELDS as $field) {
            $property = AbstractModel::propertyFromColumn($field);
            if ((string) $before->$property !== (string) $after->$property) {
                $changed = true;
                break;
            }
        }
        if (!$changed) {
            return null;
        }

        $created = $this->container->get(PaymentPlanService::class)->rebuildPlan($id);

        return $created === null
            ? "Modifiche salvate. Le scadenze non sono cambiate perche' una parte e' gia' stata incassata: correggile dallo scadenzario."
            : 'Modifiche salvate: scadenze rifatte con il nuovo piano.';
    }

    public function renewAction(): ?string
    {
        $id = (int) $this->param('id');
        $created = $this->container->get(PaymentPlanService::class)->renew($id);
        $service = $this->repository()->find($id);

        $message = $created > 0 && $service instanceof CustomerService
            ? 'Rinnovato: prossimo rinnovo il ' . F::longDate($service->renewalDate) . ', ' . F::plural($created, 'scadenza aggiunta', 'scadenze aggiunte') . '.'
            : 'Questo servizio non si puo\' rinnovare (non e\' ricorrente o non e\' attivo).';

        return $this->renderPage(
            $this->container->get(EditCustomerServiceTool::class)->buildPage($service, $message, $created > 0 ? 'success' : 'error'),
            ['title' => $this->editPageTitle()]
        );
    }

    public function rebuildAction(): ?string
    {
        $id = (int) $this->param('id');
        $created = $this->container->get(PaymentPlanService::class)->rebuildPlan($id);

        $message = $created === null
            ? "Non le ho rifatte: una parte e' gia' stata incassata. Correggi le singole scadenze dallo scadenzario."
            : 'Scadenze rifatte: ' . F::plural($created, 'scadenza', 'scadenze') . ' da incassare.';

        return $this->renderPage(
            $this->container->get(EditCustomerServiceTool::class)->buildPage($this->repository()->find($id), $message, $created === null ? 'error' : 'success'),
            ['title' => $this->editPageTitle()]
        );
    }
}
