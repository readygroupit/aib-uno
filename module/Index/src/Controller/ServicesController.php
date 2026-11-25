<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Package\PackageCatalogService;
use App\Prompt\Tool\EditServiceTool;
use App\Prompt\Tool\ListServicesTool;
use App\Repository\ServiceRepository;

final class ServicesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return ServiceRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditServiceTool::class;
    }

    protected function listToolClass(): string
    {
        return ListServicesTool::class;
    }

    protected function packageName(): string
    {
        return 'services';
    }

    protected function listPageTitle(): string
    {
        return 'Servizi';
    }

    protected function newPageTitle(): string
    {
        return 'Nuovo servizio';
    }

    protected function editPageTitle(): string
    {
        return 'Modifica servizio';
    }

    protected function defaultsOnCreate(): array
    {
        return ['stage' => 'attivo'];
    }

    protected function createdMessage(): string
    {
        return 'Servizio creato.';
    }

    /**
     * Il processo del servizio non ha un nome suo: nome e descrizione sono
     * quelli del servizio (la pagina del servizio non li ripete).
     */
    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        if (!in_array('work_orders', PackageCatalogService::installedNames(), true)) {
            return null;
        }
        $service = $this->repository()->find($id);
        $types = $this->container->get(\App\Repository\WorkOrderTypeRepository::class);
        $type = $service !== null ? $types->findByService($id) : null;
        if ($type !== null && ($type->name !== $service->name || (string) $type->description !== (string) $service->description)) {
            $types->update((int) $type->id, ['name' => $service->name, 'description' => $service->description]);
        }

        return null;
    }

    protected function requiredPermissionForAction(string $action): ?string
    {
        return $action === 'process' ? 'services.edit' : parent::requiredPermissionForAction($action);
    }

    /**
     * Il processo del servizio: nuovo (con il nome del servizio) o uno che
     * c'e' gia' e non e' di nessun servizio (type_id).
     */
    public function processAction(): void
    {
        $service = $this->repository()->find((int) $this->param('id'));
        $types = $this->container->get(\App\Repository\WorkOrderTypeRepository::class);
        if ($service === null || $types->findByService((int) $service->id) !== null) {
            $this->json(['ok' => false, 'message' => 'Questo servizio ha gia\' il suo processo.'], 422);

            return;
        }
        $typeId = (int) $this->request->get('type_id', 0);
        if ($typeId > 0 && ($type = $types->find($typeId)) !== null && $type->serviceId === null) {
            $types->update($typeId, ['service_id' => $service->id]);
            $this->json(['ok' => true, 'message' => "Collegato il processo \u{ab}{$type->name}\u{bb}."]);

            return;
        }
        $types->insert(['name' => $service->name, 'description' => $service->description, 'stage' => 'in uso', 'service_id' => $service->id]);
        $this->json(['ok' => true, 'message' => 'Processo creato: aggiungi ruoli, date chiave e fasi.']);
    }
}
