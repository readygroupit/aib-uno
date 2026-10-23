<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\AbstractModel;
use App\Repository\CaseFileRepository;
use App\Repository\CustomerRepository;
use App\Repository\CustomerServiceRepository;
use App\Repository\ServiceRepository;
use App\Repository\WorkOrderRepository;
use App\Repository\WorkOrderTypeRepository;
use App\Repository\LeadRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;

/**
 * Endpoint di ricerca generico per i campi autocomplete che puntano a
 * un'altra entita' (vedi 'autocomplete' sul campo nel manifest e
 * public/js/components/form.js, buildAutocompleteControl) - stesso schema
 * di Index\Controller\GeoController ma parametrico su QUALE entita' cercare
 * invece di un endpoint dedicato per ciascuna. 'source' e' una delle chiavi
 * fisse qui sotto (non il nome tabella grezzo preso dalla richiesta): cosi'
 * non si puo' mai cercare/leggere una tabella arbitraria dall'esterno.
 *
 * Ogni source dichiara anche il permesso di lettura richiesto: cercare
 * per nome non deve aggirare i permessi granulari di 'view' di quel
 * dominio (es. un operatore senza leads.view non deve poter comunque
 * "scoprire" nomi di lead digitando nel campo di un'altra entita').
 */
final class EntityReferenceController extends AuthController
{
    /** @var array<string, array{repository:class-string, columns:list<string>, fallback?:string, permission:?string, fill?:array<string,string>}> */
    private const SOURCES = [
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

    public function searchAction(): void
    {
        $source = self::SOURCES[(string) $this->param('source')] ?? null;
        $auth = $this->container->get(AuthService::class);
        if ($source === null || ($source['permission'] !== null && !$auth->hasPermission($source['permission']))) {
            $this->json([]);

            return;
        }

        $repo = $this->container->get($source['repository']);

        $id = trim((string) $this->param('id', ''));
        if ($id !== '') {
            $model = $repo->find((int) $id);
            $this->json($model === null ? [] : $this->toOption($model, $source));

            return;
        }

        $query = trim((string) $this->param('q', ''));
        if (mb_strlen($query) < 2) {
            $this->json([]);

            return;
        }

        $columns = $source['columns'];
        if (isset($source['fallback'])) {
            $columns[] = $source['fallback'];
        }

        $matches = $repo->searchFreeText($columns, $query, 10, $source['columns']);
        $this->json(array_map(fn (AbstractModel $model) => $this->toOption($model, $source), $matches));
    }

    /** @param array{columns:list<string>,fallback?:string} $source */
    private function toOption(AbstractModel $model, array $source): array
    {
        $label = method_exists($model, 'displayName') ? $model->displayName() : $this->labelFor($model, $source);
        $option = ['value' => $model->id, 'label' => $label];

        if (isset($source['fill'])) {
            $fill = [];
            foreach ($source['fill'] as $field => $column) {
                $value = $model->{AbstractModel::propertyFromColumn($column)} ?? null;
                if ($value !== null && $value !== '') {
                    $fill[$field] = (string) $value;
                }
            }
            $option['fill'] = $fill;
        }

        return $option;
    }

    /** @param array{columns:list<string>,fallback?:string} $source */
    private function labelFor(AbstractModel $model, array $source): string
    {
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
