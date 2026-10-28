<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Repository\CaseFileRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Repository\LeadRepository;
use App\Repository\MunicipalityRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeRepository;
use App\Service\AuthService;

/**
 * Le entita' a cui puo' puntare un campo autocomplete ('source' in
 * /riferimenti/:source/cerca, vedi Index\Controller\EntityReferenceController)
 * e il passo inverso: da un nome scritto a parole ("Fabrizio", "Rossi Srl")
 * all'id, per i form aperti dalla chat gia' compilati.
 *
 * 'source' e' una delle chiavi fisse qui sotto (non il nome tabella grezzo
 * preso dalla richiesta): cosi' non si puo' mai cercare/leggere una tabella
 * arbitraria dall'esterno. Ogni source dichiara anche il permesso di
 * lettura richiesto: cercare per nome non deve aggirare i permessi
 * granulari di 'view' di quel dominio (es. un operatore senza leads.view
 * non deve poter comunque "scoprire" nomi di lead digitando nel campo di
 * un'altra entita').
 */
final class ReferenceResolver
{
    /** @var array<string, array{repository:class-string, columns:list<string>, fallback?:string, permission:?string, fill?:array<string,string>}> */
    public const SOURCES = [
        'customers' => [
            'repository' => CustomerRepository::class,
            'columns' => ['company_name', 'first_name', 'last_name'],
            'permission' => 'customers.view',
        ],
        // I colleghi, per "chi lo segue": nomi che chiunque in azienda
        // conosce gia', non serve il permesso di gestire gli utenti.
        'staff' => [
            'repository' => UserRepository::class,
            'columns' => ['first_name', 'last_name'],
            'fallback' => 'username',
            'permission' => null,
        ],
        'services' => [
            'repository' => ServiceRepository::class,
            'columns' => ['name'],
            'permission' => 'services.view',
            // Scelto un servizio del listino, il form si compila da solo
            // con i suoi dati (solo i campi ancora vuoti, vedi form.js).
            'fill' => ['title' => 'name', 'price' => 'price', 'billing_type' => 'billing_type', 'recurrence' => 'recurrence', 'cost_price' => 'cost_price', 'description' => 'name', 'unit_price' => 'price'],
        ],
        'work_order_types' => [
            'repository' => WorkOrderTypeRepository::class,
            'columns' => ['name'],
            'permission' => 'work_orders.view',
        ],
        'work_orders' => [
            'repository' => WorkOrderRepository::class,
            'columns' => ['title'],
            'permission' => 'work_orders.view',
        ],
        'customer_services' => [
            'repository' => CustomerServiceRepository::class,
            'columns' => ['title'],
            'permission' => 'customer_services.view',
        ],
        'leads' => [
            'repository' => LeadRepository::class,
            'columns' => ['first_name', 'last_name'],
            'fallback' => 'company_name',
            'permission' => 'leads.manage',
        ],
        'users' => [
            'repository' => UserRepository::class,
            'columns' => ['first_name', 'last_name'],
            'fallback' => 'username',
            'permission' => 'users.manage',
        ],
        'cases' => [
            'repository' => CaseFileRepository::class,
            'columns' => ['title'],
            'permission' => 'cases.view',
        ],
    ];

    public function __construct(private readonly Container $container)
    {
    }

    /** La source, se esiste e chi e' collegato puo' leggerla. */
    public function source(string $key): ?array
    {
        $source = self::SOURCES[$key] ?? null;
        if ($source === null) {
            return null;
        }
        if ($source['permission'] !== null && !$this->container->get(AuthService::class)->hasPermission($source['permission'])) {
            return null;
        }

        return $source;
    }

    /**
     * Id dell'elemento che corrisponde al nome, cercato come farebbe
     * l'operatore nel campo; null se non c'e' o se e' ambiguo (due "Sara":
     * meglio lasciare scegliere che indovinare).
     *
     * @param string $url la 'source' del campo nel manifest (es. '/riferimenti/staff/cerca')
     */
    public function resolve(string $url, string $text): ?int
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (ctype_digit($text)) {
            return (int) $text;
        }

        if ($url === '/geo/comuni/cerca') {
            $rows = $this->container->get(MunicipalityRepository::class)->search($text, 5);
            foreach ($rows as $row) {
                if (strcasecmp((string) $row['name'], $text) === 0) {
                    return (int) $row['id'];
                }
            }

            return count($rows) === 1 ? (int) $rows[0]['id'] : null;
        }

        if (preg_match('#^/riferimenti/([a-z_]+)/cerca$#', $url, $m) !== 1 || ($source = $this->source($m[1])) === null) {
            return null;
        }

        $columns = $source['columns'];
        if (isset($source['fallback'])) {
            $columns[] = $source['fallback'];
        }
        $matches = $this->container->get($source['repository'])->searchFreeText($columns, $text, 5, $source['columns']);
        if (count($matches) === 1) {
            return (int) $matches[0]->id;
        }
        foreach ($matches as $model) {
            if (strcasecmp($this->label($model, $source), $text) === 0) {
                return (int) $model->id;
            }
        }

        return null;
    }

    /** @param array{columns:list<string>,fallback?:string} $source */
    public function label(AbstractModel $model, array $source): string
    {
        if (method_exists($model, 'displayName')) {
            return $model->displayName();
        }

        $parts = [];
        foreach ($source['columns'] as $column) {
            $value = trim((string) ($model->{AbstractModel::propertyFromColumn($column)} ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        if ($parts === [] && isset($source['fallback'])) {
            $value = trim((string) ($model->{AbstractModel::propertyFromColumn($source['fallback'])} ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? "#{$model->id}" : implode(' ', $parts);
    }
}
