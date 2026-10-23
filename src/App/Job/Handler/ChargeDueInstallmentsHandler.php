<?php

declare(strict_types=1);

namespace App\Job\Handler;

use App\Core\Container;
use App\Job\JobHandlerInterface;
use App\Payments\PaymentService;

/**
 * job_type 'charge_due_installments': addebita con Stripe le scadenze
 * arrivate dei clienti con il pagamento automatico attivo (carta o
 * mandato SEPA salvati dall'area cliente). Una volta al giorno, dopo i
 * rinnovi. Senza Stripe collegato non fa nulla.
 */
final class ChargeDueInstallmentsHandler implements JobHandlerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function handle(array $payload): array
    {
        $outcome = $this->container->get(PaymentService::class)->chargeDue(date('Y-m-d'));

        return [
            'status' => 'success',
            'result' => "Addebitati: {$outcome['charged']}, in corso (SEPA): {$outcome['pending']}, non riusciti: {$outcome['failed']}",
        ];
    }
}
