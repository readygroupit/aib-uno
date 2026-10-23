<?php

declare(strict_types=1);

namespace App\ClientHealth;

use App\Core\Container;
use App\Model\Customer;
use App\Model\CustomerService;
use App\Model\CustomerTouchpoint;
use App\Model\WorkOrder;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Repository\CustomerTouchpointRepository;
use App\Repository\InstallmentRepository;
use App\Repository\WorkOrderRepository;
use App\Support\ItalianFormat as F;

/**
 * La temperatura di un cliente: da 100 si tolgono punti per ogni segnale
 * di raffreddamento, dai dati che ci sono gia':
 * - da quanto non lo si sente (registro dei contatti);
 * - com'era l'ultima volta (contento, neutro, freddo, scontento);
 * - servizi che non danno risultati;
 * - pagamenti in ritardo;
 * - commesse ferme ad aspettare lui.
 * Un rinnovo vicino non toglie punti ma rende il rischio urgente: e' il
 * momento in cui un cliente freddo se ne va.
 *
 * Solo per i clienti veri (rapporto 'cliente') con almeno un servizio
 * attivo: un fornitore o un ex cliente non ha una temperatura da seguire.
 * Le soglie sono costanti qui: si tarano con l'uso.
 */
final class HealthScore
{
    public const SILENT_WARN_DAYS = 25;
    public const SILENT_RISK_DAYS = 45;
    public const RENEWAL_ALERT_DAYS = 45;
    private const MOOD_PENALTY = ['scontento' => 35, 'freddo' => 20, 'neutro' => 5, 'contento' => 0];
    private const LEVELS = [
        'bene' => ['label' => 'Bene', 'tone' => 'success'],
        'seguire' => ['label' => 'Da seguire', 'tone' => 'warning'],
        'rischio' => ['label' => 'A rischio', 'tone' => 'danger'],
    ];

    private Container $container;
    private string $today;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->today = date('Y-m-d');
    }

    public static function isAvailable(): bool
    {
        return in_array('client_health', PackageCatalogService::installedNames(), true);
    }

    /**
     * Tutti i clienti seguiti, dal piu' a rischio.
     *
     * @return array<int, array{customerId:int, name:string, score:int, level:string, label:string, tone:string, reasons:list<string>, suggestion:string, lastContact:?string, renewal:?string, accountUserId:?int}>
     */
    public function all(): array
    {
        $installed = PackageCatalogService::installedNames();
        // Rapporto non indicato: e' un cliente (come nella lista clienti).
        $customers = array_filter(
            $this->container->get(CustomerRepository::class)->findAll(),
            static fn (Customer $c) => $c->relationship === null || $c->relationship === 'cliente'
        );
        $services = [];
        if (in_array('customer_services', $installed, true)) {
            foreach ($this->container->get(CustomerServiceRepository::class)->findActive() as $service) {
                $services[(int) $service->customerId][] = $service;
            }
        }
        $last = $this->container->get(CustomerTouchpointRepository::class)->lastPerCustomer();
        $money = in_array('installments', $installed, true) ? $this->container->get(InstallmentRepository::class)->openSummaryByCustomer($this->today) : [];
        $waiting = [];
        if (in_array('work_orders', $installed, true)) {
            foreach ($this->container->get(WorkOrderRepository::class)->findAll(['stage' => 'in attesa del cliente']) as $order) {
                $waiting[(int) $order->customerId][] = $order;
            }
        }

        $result = [];
        foreach ($customers as $customer) {
            $id = (int) $customer->id;
            if (!isset($services[$id])) {
                continue;
            }
            $result[$id] = $this->evaluate($customer, $services[$id], $last[$id] ?? null, $money[$id] ?? null, $waiting[$id] ?? []);
        }
        uasort($result, static fn (array $a, array $b) => $a['score'] <=> $b['score']);

        return $result;
    }

    public function forCustomer(Customer $customer): ?array
    {
        return $this->all()[(int) $customer->id] ?? null;
    }

    /**
     * @param CustomerService[] $services
     * @param WorkOrder[] $waiting
     */
    private function evaluate(Customer $customer, array $services, ?CustomerTouchpoint $last, ?array $money, array $waiting): array
    {
        $score = 100;
        $reasons = [];
        // Cosa suggerire dipende dal segnale piu' serio, non dal primo trovato.
        $signals = [];

        // Da quanto non lo si sente.
        $silentDays = $last !== null ? -F::daysFrom((string) $last->contactDate, $this->today) : null;
        if ($silentDays === null) {
            $score -= 15;
            $reasons[] = 'nessun contatto registrato';
            $signals['call'] = true;
        } elseif ($silentDays >= self::SILENT_RISK_DAYS) {
            $score -= 25;
            $reasons[] = "non lo sentite da {$silentDays} giorni";
            $signals['call'] = true;
        } elseif ($silentDays >= self::SILENT_WARN_DAYS) {
            $score -= 12;
            $reasons[] = "ultimo contatto {$silentDays} giorni fa";
        }

        // Com'era l'ultima volta.
        if ($last !== null && ($penalty = self::MOOD_PENALTY[$last->mood] ?? 0) > 0) {
            $score -= $penalty;
            if ($last->mood !== 'neutro') {
                $reasons[] = "l'ultima volta era {$last->mood}" . ($last->summary ? " (\u{ab}" . mb_strimwidth($last->summary, 0, 70, "\u{2026}") . "\u{bb})" : '');
                $signals[$last->mood === 'scontento' ? 'meeting' : 'call'] = true;
            }
        }

        // Servizi che non rendono.
        foreach ($services as $service) {
            if ($service->results === 'scarsi') {
                $score -= 20;
                $reasons[] = "risultati scarsi su \u{ab}{$service->title}\u{bb}";
                $signals['results'] = true;
            } elseif ($service->results === 'parziali') {
                $score -= 8;
                $reasons[] = "risultati parziali su \u{ab}{$service->title}\u{bb}";
            }
        }

        // Pagamenti.
        if ($money !== null && $money['overdue'] > 0) {
            $late = $money['oldestOverdue'] !== null ? -F::daysFrom((string) $money['oldestOverdue'], $this->today) : 0;
            $score -= $late > 30 ? 25 : 12;
            $reasons[] = 'pagamenti scaduti per ' . F::euro($money['overdue']) . ($late > 0 ? " (da {$late} giorni)" : '');
            $signals['payment'] = true;
        }

        // Commesse ferme ad aspettarlo.
        foreach ($waiting as $order) {
            $days = $order->waitingSince !== null ? -F::daysFrom((string) $order->waitingSince, $this->today) : 0;
            if ($days >= 10) {
                $score -= 10;
                $reasons[] = "\u{ab}{$order->title}\u{bb} aspetta lui da {$days} giorni";
                $signals['waiting'] = true;
            }
        }

        // Il rinnovo piu' vicino.
        $renewal = null;
        foreach ($services as $service) {
            if ($service->isRecurring() && $service->renewalDate !== null && ($renewal === null || $service->renewalDate < $renewal->renewalDate)) {
                $renewal = $service;
            }
        }
        $renewalDays = $renewal !== null ? F::daysFrom((string) $renewal->renewalDate, $this->today) : null;

        $main = null;
        foreach (['meeting', 'results', 'call', 'payment', 'waiting'] as $signal) {
            if (isset($signals[$signal])) {
                $main = $signal;
                break;
            }
        }

        $score = max(0, min(100, $score));
        $level = $score >= 75 ? 'bene' : ($score >= 50 ? 'seguire' : 'rischio');
        // Scontento di recente: e' a rischio qualunque sia il resto.
        if ($last !== null && $last->mood === 'scontento' && $silentDays !== null && $silentDays <= 60) {
            $level = 'rischio';
        }
        // Tiepido a ridosso del rinnovo: e' gia' rischio.
        if ($level === 'seguire' && $renewalDays !== null && $renewalDays <= self::RENEWAL_ALERT_DAYS) {
            $level = 'rischio';
        }
        if ($renewalDays !== null && $renewalDays <= self::RENEWAL_ALERT_DAYS && $level !== 'bene') {
            $reasons[] = 'rinnova ' . F::relativeDays((string) $renewal->renewalDate, $this->today) . " (\u{ab}{$renewal->title}\u{bb})";
        }

        return [
            'customerId' => (int) $customer->id,
            'name' => $customer->displayName(),
            'href' => '/clienti/' . $customer->id,
            'score' => $score,
            'level' => $level,
            'label' => self::LEVELS[$level]['label'],
            'tone' => self::LEVELS[$level]['tone'],
            'reasons' => $reasons,
            'suggestion' => $this->suggestion($level, $main, $renewalDays, $customer),
            'lastContact' => $last !== null ? F::relativeDays((string) $last->contactDate, $this->today) . ($last->mood ? ", {$last->mood}" : '') : null,
            'renewal' => $renewal !== null ? F::shortDate($renewal->renewalDate, $this->today) : null,
            'accountUserId' => $customer->accountUserId,
            'silentDays' => $silentDays,
        ];
    }

    private function suggestion(string $level, ?string $main, ?int $renewalDays, Customer $customer): string
    {
        if ($level === 'bene' && ($main === null || $main === 'call')) {
            return 'Tutto bene: continuate cosi\'.';
        }
        $before = $renewalDays !== null && $renewalDays <= self::RENEWAL_ALERT_DAYS ? ' prima del rinnovo' : '';

        return match ($main) {
            'meeting' => "Fissa un incontro{$before}: l'ultimo contatto e' andato male, meglio parlarne di persona.",
            'results' => "Prepara due numeri sui risultati e proponi come migliorarli{$before}.",
            'payment' => 'Senti l\'amministrazione per i pagamenti, con garbo: spesso un ritardo nasconde un malumore.',
            'waiting' => 'Chiamalo per sbloccare cio\' che aspettiamo da lui: un lavoro fermo raffredda anche il cliente.',
            default => "Fagli una telefonata{$before}: due minuti per sapere come va.",
        };
    }
}
