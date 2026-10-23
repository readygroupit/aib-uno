<?php

declare(strict_types=1);

namespace App\ClientHealth;

use App\Core\Container;
use App\Model\CustomerTouchpoint;
use App\Repository\CustomerRepository;
use App\Repository\CustomerTouchpointRepository;
use App\Repository\UserRepository;
use App\Support\ItalianFormat as F;

/**
 * Temperatura dei clienti da mostrare: la lista per rischio, gli ultimi
 * contatti, e il riquadro della scheda cliente.
 */
final class HealthOverviewService
{
    public const MOOD_TONES = ['contento' => 'success', 'neutro' => 'neutral', 'freddo' => 'warning', 'scontento' => 'danger'];

    private HealthScore $score;
    private CustomerTouchpointRepository $touchpoints;
    private Container $container;
    private string $today;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->score = $container->get(HealthScore::class);
        $this->touchpoints = $container->get(CustomerTouchpointRepository::class);
        $this->today = date('Y-m-d');
    }

    public function board(): array
    {
        $all = $this->score->all();
        $counts = ['rischio' => 0, 'seguire' => 0, 'bene' => 0];
        foreach ($all as $row) {
            $counts[$row['level']]++;
        }
        $recent = $this->touchpoints->findRecent(12);

        return [
            'counts' => $counts,
            'rows' => array_values(array_map(fn (array $r) => $r + ['logHref' => '/clienti/contatti/nuovo?customer_id=' . $r['customerId']], $all)),
            'recent' => $this->touchpointItems($recent, true),
        ];
    }

    /** Riquadro "Temperatura" della scheda cliente. */
    public function forCustomer(int $customerId): ?array
    {
        $customer = $this->container->get(CustomerRepository::class)->find($customerId);
        if ($customer === null) {
            return null;
        }
        $health = $this->score->forCustomer($customer);

        return [
            'health' => $health,
            'touchpoints' => $this->touchpointItems($this->touchpoints->findByCustomer($customerId, 5), false),
            'logHref' => '/clienti/contatti/nuovo?customer_id=' . $customerId,
        ];
    }

    /** @param CustomerTouchpoint[] $touchpoints */
    private function touchpointItems(array $touchpoints, bool $withCustomer): array
    {
        $names = [];
        if ($withCustomer) {
            foreach ($this->container->get(CustomerRepository::class)->findAll() as $customer) {
                $names[(int) $customer->id] = $customer->displayName();
            }
        }
        $people = [];
        foreach ($this->container->get(UserRepository::class)->findAll() as $user) {
            $people[(int) $user->id] = trim("{$user->firstName} {$user->lastName}");
        }

        return array_map(fn (CustomerTouchpoint $t) => [
            'href' => '/clienti/contatti/' . $t->id,
            'customer' => $names[(int) $t->customerId] ?? null,
            'customerHref' => '/clienti/' . $t->customerId,
            'date' => F::shortDate($t->contactDate, $this->today),
            'channel' => (string) $t->channel,
            'mood' => (string) $t->mood,
            'moodTone' => self::MOOD_TONES[$t->mood] ?? 'neutral',
            'summary' => (string) $t->summary,
            'next' => $t->nextStep ? $t->nextStep . ($t->nextDate ? ' entro ' . F::shortDate($t->nextDate, $this->today) : '') : null,
            'who' => $people[(int) $t->userId] ?? null,
        ], $touchpoints);
    }
}
