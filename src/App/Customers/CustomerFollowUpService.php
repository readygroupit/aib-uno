<?php

declare(strict_types=1);

namespace App\Customers;

use App\Assistant\AssistantService;
use App\Core\Container;
use App\Model\Customer;
use App\Package\PackageCatalogService;
use App\Repository\CustomerRepository;
use App\Repository\CustomerTeamMemberRepository;

/**
 * Quando risentire un cliente. La data la propone la temperatura (caldo
 * presto, freddo piu' avanti: giorni delle regole contact_hot/warm/cold
 * dell'assistente), si sposta da sola dopo ogni contatto e si puo' sempre
 * cambiare a mano. Lo stato commerciale segue il preventivo.
 */
final class CustomerFollowUpService
{
    private const DEFAULT_DAYS = ['caldo' => 3, 'tiepido' => 10, 'freddo' => 30];
    private const RULE_BY_TEMPERATURE = ['caldo' => 'contact_hot', 'tiepido' => 'contact_warm', 'freddo' => 'contact_cold'];

    public function __construct(private readonly Container $container)
    {
    }

    /** Dopo quanti giorni risentire un cliente con questa temperatura. */
    public function intervalFor(?string $temperature): int
    {
        $temperature = isset(self::DEFAULT_DAYS[(string) $temperature]) ? (string) $temperature : 'tiepido';
        if (in_array('assistant', PackageCatalogService::installedNames(), true)) {
            $rule = $this->container->get(AssistantService::class)->rule(self::RULE_BY_TEMPERATURE[$temperature]);
            if ($rule['enabled'] && $rule['days'] !== null) {
                return max(1, (int) $rule['days']);
            }
        }

        return self::DEFAULT_DAYS[$temperature];
    }

    /** Si segue (si risente) un cliente o un potenziale; fornitori e collaboratori no. */
    public function isFollowed(Customer $customer): bool
    {
        return $customer->commercialStage !== 'ex cliente'
            && ($customer->commercialStage !== null || in_array($customer->relationship, [null, '', 'cliente'], true));
    }

    /**
     * Propone la data se manca (o se e' cambiata la temperatura): mai sopra
     * una data scelta a mano piu' vicina.
     *
     * @return ?string la data proposta, null se non serviva
     */
    public function propose(int $customerId, bool $temperatureChanged = false): ?string
    {
        $customer = $this->customers()->find($customerId);
        if (!$customer instanceof Customer || !$this->isFollowed($customer)) {
            return null;
        }
        $date = date('Y-m-d', strtotime('+' . $this->intervalFor($customer->temperature) . ' days'));
        if ($customer->nextContactDate !== null && !($temperatureChanged && $date < $customer->nextContactDate)) {
            return null;
        }
        $this->customers()->update($customerId, ['next_contact_date' => $date]);

        return $date;
    }

    /** C'e' stato un contatto: la prossima data e' quella detta, o si ripropone dalla temperatura. */
    public function contacted(int $customerId, ?string $nextDate = null, ?string $nextNote = null): void
    {
        $customer = $this->customers()->find($customerId);
        if (!$customer instanceof Customer || !$this->isFollowed($customer)) {
            return;
        }
        $this->customers()->update($customerId, [
            'next_contact_date' => $nextDate ?? date('Y-m-d', strtotime('+' . $this->intervalFor($customer->temperature) . ' days')),
            'next_contact_note' => $nextNote !== null && trim($nextNote) !== '' ? mb_substr(trim($nextNote), 0, 255) : null,
        ]);
    }

    /** "Rimanda": un altro giro della sua temperatura. */
    public function postpone(int $customerId): ?string
    {
        $customer = $this->customers()->find($customerId);
        if (!$customer instanceof Customer) {
            return null;
        }
        $date = date('Y-m-d', strtotime('+' . $this->intervalFor($customer->temperature) . ' days'));
        $this->customers()->update($customerId, ['next_contact_date' => $date]);

        return $date;
    }

    /** Preventivo in corso: da potenziale a "in trattativa". */
    public function negotiating(int $customerId): void
    {
        $customer = $this->customers()->find($customerId);
        if ($customer instanceof Customer && in_array($customer->commercialStage, [null, '', 'potenziale', 'ex cliente'], true)) {
            $this->customers()->update($customerId, ['commercial_stage' => 'in trattativa']);
        }
    }

    public function won(int $customerId): void
    {
        $this->customers()->update($customerId, ['commercial_stage' => 'cliente']);
    }

    /** Chi segue il cliente: il responsabile, altrimenti il referente o il primo della sua squadra. */
    public function ownerOf(Customer $customer): ?int
    {
        if ($customer->accountUserId !== null) {
            return (int) $customer->accountUserId;
        }
        if (!in_array('customers', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $team = $this->container->get(CustomerTeamMemberRepository::class)->findByCustomer((int) $customer->id);
        foreach ($team as $member) {
            if ($member->role === 'referente') {
                return (int) $member->userId;
            }
        }

        return isset($team[0]) ? (int) $team[0]->userId : null;
    }

    private function customers(): CustomerRepository
    {
        return $this->container->get(CustomerRepository::class);
    }
}
