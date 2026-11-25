<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\WorkOrderTypeMilestone;
use App\Model\WorkOrderTypeRole;
use App\Model\WorkOrderTypeStep;
use App\Repository\WorkOrderTypeMilestoneRepository;
use App\Repository\WorkOrderTypeRoleRepository;
use App\Repository\WorkOrderTypeStepRepository;

/**
 * Le parti di un processo (ruoli, date chiave, cose da consegnare) e di un
 * progetto (squadra, date, modifiche): si modificano dal loro form, ma
 * dopo il salvataggio si torna alla pagina del processo o del progetto,
 * dove si vedono insieme alle altre.
 *
 * I campi che scelgono un ruolo, una fase o una data chiave dello stesso
 * processo diventano un menu con solo quelli (non una ricerca su tutti i
 * processi): dynamicOptions().
 */
abstract class AbstractProcessChildTool extends AbstractEditEntityTool
{
    protected Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
    }

    /** La pagina del processo o del progetto a cui appartiene, con l'esito in cima. */
    abstract protected function parentPage(AbstractModel $model, string $message): ?array;

    /** @return array<string, list<array{value:string,label:string}>> campo => scelte */
    protected function dynamicOptions(AbstractModel $model): array
    {
        return [];
    }

    protected function packageName(): string
    {
        return 'work_orders';
    }

    public function requiredPermission(): ?string
    {
        return 'work_orders.edit';
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        if ($model->id !== null && $message !== null && $fieldErrors === [] && $messageType !== 'error') {
            $page = $this->parentPage($model, $message);
            if ($page !== null) {
                return $page;
            }
        }

        return parent::buildPage($model, $message, $messageType, $fieldErrors);
    }

    protected function fieldsFromManifest(AbstractModel $model, array $fieldErrors = []): array
    {
        $fields = parent::fieldsFromManifest($model, $fieldErrors);
        $options = $this->dynamicOptions($model);
        foreach ($fields as &$field) {
            if (!isset($options[$field['key']]) || $field['inputType'] === 'hidden') {
                continue;
            }
            $field['inputType'] = 'select';
            $field['options'] = $options[$field['key']];
            $field['autocompleteSource'] = null;
        }

        return $fields;
    }

    /** @return list<array{value:string,label:string}> */
    protected function roleOptions(?int $typeId): array
    {
        if ($typeId === null) {
            return [];
        }

        return array_map(
            static fn (WorkOrderTypeRole $r) => ['value' => (string) $r->id, 'label' => (string) $r->label],
            $this->container->get(WorkOrderTypeRoleRepository::class)->findByWorkOrderType($typeId)
        );
    }

    /** @return list<array{value:string,label:string}> */
    protected function stepOptions(?int $typeId, ?int $exceptId = null): array
    {
        if ($typeId === null) {
            return [];
        }
        $steps = array_filter(
            $this->container->get(WorkOrderTypeStepRepository::class)->findByType($typeId),
            static fn (WorkOrderTypeStep $s) => (int) $s->id !== $exceptId
        );

        return array_values(array_map(
            static fn (WorkOrderTypeStep $s) => ['value' => (string) $s->id, 'label' => trim(($s->position !== null ? $s->position . '. ' : '') . $s->title)],
            $steps
        ));
    }

    /** @return list<array{value:string,label:string}> */
    protected function milestoneOptions(?int $typeId): array
    {
        if ($typeId === null) {
            return [];
        }

        return array_map(
            static fn (WorkOrderTypeMilestone $m) => ['value' => (string) $m->id, 'label' => (string) $m->label],
            $this->container->get(WorkOrderTypeMilestoneRepository::class)->findByWorkOrderType($typeId)
        );
    }

    protected function searchColumns(): array
    {
        return ['label'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'label', 'label' => 'Nome']];
    }
}
