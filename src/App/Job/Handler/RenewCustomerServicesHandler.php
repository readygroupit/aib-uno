<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Billing\PaymentPlanService;
use App\Core\Container;
use App\Job\JobHandlerInterface;

/**
 * job_type 'renew_customer_services': rinnova i servizi con rinnovo
 * automatico arrivati a scadenza e aggiunge allo scadenzario gli incassi
 * del nuovo periodo. Pensato una volta al giorno (interval_minutes 1440).
 * L'addebito, per chi ha il pagamento automatico, lo fa poi
 * ChargeDueInstallmentsHandler alla scadenza.
 */
final class RenewCustomerServicesHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $outcome = $this->container->get(PaymentPlanService::class)->runAutoRenewals(date('Y-m-d'));

        return [
            'status' => 'success',
            'result' => "Servizi rinnovati: {$outcome['services']}, scadenze create: {$outcome['installments']}",
        ];
    }
}
