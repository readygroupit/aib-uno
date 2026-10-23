<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Scheda del cliente: chi e', quanto vale, cosa ha attivo, cosa deve
 * pagare, chi sono i referenti e la cosa da fare adesso. Dati da
 * App\Billing\AccountOverviewService::customerProfile(); il form dei dati
 * anagrafici segue sotto, chiuso.
 */
final class CustomerProfileComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'customer-profile',
            'url' => $config['url'] ?? null,
            'customer' => $config['customer'] ?? [],
            'services' => $config['services'] ?? [],
            'installments' => $config['installments'] ?? [],
            'contacts' => $config['contacts'] ?? [],
            'referrer' => $config['referrer'] ?? null,
            'brought' => $config['brought'] ?? [],
            'showBilling' => (bool) ($config['showBilling'] ?? true),
            'actions' => $config['actions'] ?? [],
            'workOrders' => $config['workOrders'] ?? null,
            'appointments' => $config['appointments'] ?? null,
            'newAppointmentHref' => $config['newAppointmentHref'] ?? null,
            'newWorkOrderHref' => $config['newWorkOrderHref'] ?? null,
            'health' => $config['health'] ?? null,
            'team' => $config['team'] ?? [],
        ];
    }
}
