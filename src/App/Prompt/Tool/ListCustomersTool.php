<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Billing\AccountOverviewService;
use App\Core\Container;
use App\Prompt\PromptConversationalInterface;
use App\Prompt\PromptToolInterface;
use App\Repository\CustomerRepository;
use App\Support\ItalianFormat as F;
use App\View\Component\CustomerListComponent;
use App\View\Component\StatBoxComponent;

/**
 * Lista clienti generica: rapporto, servizi attivi, valore annuo, prossimo
 * rinnovo, situazione dei pagamenti. Dove ci sono le pratiche (Assilevi)
 * la sostituisce ListCustomerClaimsTool, registrato dopo con lo stesso
 * nome (vedi config/prompt-tools.php).
 */
final class ListCustomersTool implements PromptToolInterface, PromptConversationalInterface
{
    private AccountOverviewService $overview;
    private CustomerRepository $customers;
    private StatBoxComponent $statBox;
    private CustomerListComponent $list;
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->overview = $container->get(AccountOverviewService::class);
        $this->customers = $container->get(CustomerRepository::class);
        $this->statBox = $container->get(StatBoxComponent::class);
        $this->list = $container->get(CustomerListComponent::class);
    }

    public function name(): string
    {
        return 'list_customers';
    }

    public function description(): string
    {
        return "Mostra i clienti (e intermediari, fornitori, collaboratori) con servizi attivi, valore annuo, "
            . "prossimi rinnovi e pagamenti scaduti. Usalo per 'clienti', 'chi sono i clienti chiave', "
            . "'chi deve pagare', 'clienti con rinnovi vicini'.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'filter' => [
                    'type' => 'string',
                    'enum' => ['tutti', 'chiave', 'rinnovi', 'scaduti', 'intermediari', 'fornitori'],
                    'description' => 'Scheda da aprire gia\' selezionata',
                ],
            ],
        ];
    }

    public function menuLabel(): ?string
    {
        return 'Clienti';
    }

    public function menuSection(): ?string
    {
        return 'crm';
    }

    public function menuCount(): ?string
    {
        return (string) $this->customers->count();
    }

    public function requiredPermission(): ?string
    {
        return 'customers.view';
    }

    public function triggers(): array
    {
        return ['clienti', 'lista clienti', 'elenco clienti', 'i miei clienti'];
    }

    public function execute(array $input): array
    {
        $rows = $this->withMine($this->withHealth($this->overview->customerRows()));
        $totals = $this->overview->totals($rows);
        $billing = $this->overview->hasBilling();

        return [$this->list->toData([
            'title' => 'Clienti',
            'url' => '/clienti',
            'createHref' => '/clienti/nuovo',
            'createLabel' => 'Nuovo cliente',
            'stats' => $billing ? $this->stats($totals, count($rows)) : [],
            'filterTabs' => $this->filterTabs($rows, (string) ($input['filter'] ?? 'tutti'), $billing),
            'rows' => $rows,
            'showBilling' => $billing,
            'emptyMessage' => 'Ancora nessun cliente: aggiungi il primo, oppure importali da un foglio Excel.',
        ])];
    }

    public function replySummary(array $input): ?string
    {
        $rows = $this->overview->customerRows();
        if ($rows === []) {
            return 'Non ci sono ancora clienti. Puoi aggiungerne uno con "nuovo cliente".';
        }
        $t = $this->overview->totals($rows);
        $sentence = F::plural($t['clients'], 'cliente', 'clienti');
        if ($t['annual'] > 0) {
            $sentence .= ', ' . F::euro($t['annual']) . " all'anno di servizi ricorrenti";
        }
        $sentence .= '.';

        $alerts = [];
        if ($t['overdue'] > 0) {
            $alerts[] = F::plural($t['overdueCustomers'], 'cliente ha', 'clienti hanno') . ' pagamenti scaduti (' . F::euro($t['overdue']) . ')';
        }
        if ($t['renewalsSoon'] > 0) {
            $alerts[] = F::plural($t['renewalsSoon'], 'rinnovo', 'rinnovi') . ' nei prossimi ' . AccountOverviewService::RENEWAL_HORIZON_DAYS . ' giorni';
        }
        if ($alerts !== []) {
            $sentence .= ' Da guardare: ' . implode(' e ', $alerts) . '.';
        } else {
            $sentence .= ' Nessun pagamento scaduto e nessun rinnovo vicino.';
        }

        return $sentence;
    }

    public function followUpSuggestions(): array
    {
        return ['Chi deve ancora pagare?', 'Rinnovi del prossimo mese', 'Clienti chiave'];
    }

    private function stats(array $t, int $count): array
    {
        return [
            $this->statBox->toData([
                'label' => 'Servizi ricorrenti',
                'value' => F::euro($t['annual']),
                'comparison' => "all'anno, da " . F::plural($t['activeServices'], 'servizio attivo', 'servizi attivi'),
                'feature' => true,
                'badge' => 'Valore annuo',
            ]),
            $this->statBox->toData([
                'label' => 'Clienti',
                'value' => (string) $t['clients'],
                'comparison' => $count > $t['clients'] ? 'piu\' ' . ($count - $t['clients']) . ' tra intermediari e fornitori' : 'in anagrafica',
                'dotColor' => 'moss',
            ]),
            $this->statBox->toData([
                'label' => 'Rinnovi vicini',
                'value' => (string) $t['renewalsSoon'],
                'comparison' => $t['renewalsSoon'] > 0 ? F::euro($t['renewalsSoonValue']) . ' nei prossimi ' . AccountOverviewService::RENEWAL_HORIZON_DAYS . ' giorni' : 'nei prossimi ' . AccountOverviewService::RENEWAL_HORIZON_DAYS . ' giorni',
                'dotColor' => 'brass',
                'href' => '/scadenzario',
            ]),
            $this->statBox->toData([
                'label' => 'Pagamenti scaduti',
                'value' => F::euro($t['overdue']),
                'comparison' => $t['overdue'] > 0 ? 'da ' . F::plural($t['overdueCustomers'], 'cliente', 'clienti') : 'tutti in regola',
                'dotColor' => $t['overdue'] > 0 ? 'danger' : 'moss',
                'href' => '/scadenzario',
            ]),
        ];
    }

    /** La scheda "I miei": clienti di cui sono referente o in squadra. */
    private function withMine(array $rows): array
    {
        $userId = $this->container->get(\App\Service\AuthService::class)->currentUserId();
        if ($userId === null) {
            return $rows;
        }
        $mine = array_flip($this->overview->customerIdsFor($userId));
        foreach ($rows as &$row) {
            if (isset($mine[(int) $row['id']])) {
                $row['buckets'][] = 'miei';
            }
        }
        unset($row);

        return $rows;
    }

    /** Il pallino della temperatura su ogni cliente seguito, e la scheda "A rischio". */
    private function withHealth(array $rows): array
    {
        if (!\App\ClientHealth\HealthScore::isAvailable()) {
            return $rows;
        }
        $health = $this->container->get(\App\ClientHealth\HealthScore::class)->all();
        foreach ($rows as &$row) {
            $h = $health[(int) $row['id']] ?? null;
            if ($h === null) {
                continue;
            }
            $row['health'] = ['tone' => $h['tone'], 'label' => $h['label'] . ' (' . $h['score'] . '°)' . ($h['reasons'] !== [] ? ': ' . $h['reasons'][0] : '')];
            if ($h['level'] === 'rischio') {
                $row['buckets'][] = 'rischio';
            }
        }
        unset($row);

        return $rows;
    }

    private function filterTabs(array $rows, string $active, bool $billing): array
    {
        $count = static fn (string $bucket) => count(array_filter($rows, static fn (array $r) => in_array($bucket, $r['buckets'], true)));

        $tabs = [
            ['label' => 'Tutti', 'value' => 'tutti'],
            ['label' => 'I miei', 'value' => 'miei'],
            ['label' => 'Clienti chiave', 'value' => 'chiave'],
            ['label' => 'A rischio', 'value' => 'rischio'],
        ];
        if ($billing) {
            $tabs[] = ['label' => 'Rinnovi vicini', 'value' => 'rinnovi'];
            $tabs[] = ['label' => 'Pagamenti scaduti', 'value' => 'scaduti'];
        }
        $tabs[] = ['label' => 'Intermediari', 'value' => 'intermediari'];
        $tabs[] = ['label' => 'Fornitori', 'value' => 'fornitori'];

        $result = [];
        foreach ($tabs as $tab) {
            $tab['count'] = $count($tab['value']);
            // Una scheda vuota non serve, salvo "Tutti" e quella richiesta.
            if ($tab['count'] === 0 && $tab['value'] !== 'tutti' && $tab['value'] !== $active) {
                continue;
            }
            $tab['active'] = $tab['value'] === $active;
            $result[] = $tab;
        }

        return $result;
    }
}
