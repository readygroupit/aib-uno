<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Model\Communication;
use App\Model\CommunicationContent;
use App\Package\PackageManifest;
use App\Prompt\Tool\EditCommunicationTool;
use App\Prompt\Tool\ListCommunicationsTool;
use App\Repository\CommunicationContentRepository;
use App\Repository\CommunicationRepository;
use App\Validation\FieldValidator;

/**
 * Bespoke apposta (non su AbstractEntityController): stesso motivo di
 * EditCommunicationTool, due tabelle per una sola entita' percepita
 * dall'operatore. GET mostra, POST rielabora sulla stessa rotta - stessa
 * forma di ogni altro controller CRUD del framework, solo con due
 * repository invece di uno.
 */
final class CommunicationsController extends AuthController
{
    protected function requiredPermissionForAction(string $action): ?string
    {
        return match ($action) {
            'index' => 'communications.view',
            'new' => 'communications.create',
            'edit' => 'communications.edit',
            'delete' => 'communications.delete',
            default => null,
        };
    }

    public function indexAction(): ?string
    {
        $page = max(1, (int) $this->param('page', 1));

        $components = $this->container->get(ListCommunicationsTool::class)->execute(['page' => $page]);

        return $this->renderPage($components, ['title' => 'Comunicazioni']);
    }

    public function newAction(): ?string
    {
        /** @var EditCommunicationTool $tool */
        $tool = $this->container->get(EditCommunicationTool::class);

        if ($this->request->getMethod() !== 'POST') {
            return $this->renderPage(
                [$tool->buildForm(new Communication(), new CommunicationContent())],
                ['title' => 'Nuova comunicazione']
            );
        }

        [$commData, $commErrors] = $this->collectAndValidate('communications');
        [$contentData, $contentErrors] = $this->collectAndValidate('communication_contents');
        $fieldErrors = $commErrors + $contentErrors;

        if ($fieldErrors !== []) {
            $components = [$tool->buildForm(
                Communication::fromArray($commData),
                CommunicationContent::fromArray($contentData),
                null,
                null,
                $fieldErrors
            )];
        } else {
            $communications = $this->container->get(CommunicationRepository::class);
            $contents = $this->container->get(CommunicationContentRepository::class);

            $id = $communications->insert($commData);
            $contentData['communication_id'] = (string) $id;
            $contents->insert($contentData);

            $components = [$tool->buildForm(
                $communications->find($id),
                $contents->findByCommunicationId($id),
                'Comunicazione registrata.',
                'success'
            )];
        }

        return $this->renderPage($components, ['title' => 'Nuova comunicazione']);
    }

    public function editAction(): ?string
    {
        $id = (int) $this->param('id');

        $communications = $this->container->get(CommunicationRepository::class);
        $contents = $this->container->get(CommunicationContentRepository::class);
        /** @var EditCommunicationTool $tool */
        $tool = $this->container->get(EditCommunicationTool::class);

        $communication = $communications->find($id);
        if ($communication === null) {
            return $this->renderPage($tool->execute(['id' => $id]), ['title' => 'Modifica comunicazione']);
        }

        if ($this->request->getMethod() !== 'POST') {
            $content = $contents->findByCommunicationId($id) ?? new CommunicationContent();

            return $this->renderPage([$tool->buildForm($communication, $content)], ['title' => 'Modifica comunicazione']);
        }

        [$commData, $commErrors] = $this->collectAndValidate('communications');
        [$contentData, $contentErrors] = $this->collectAndValidate('communication_contents');
        $fieldErrors = $commErrors + $contentErrors;

        $existingContent = $contents->findByCommunicationId($id) ?? new CommunicationContent();

        if ($fieldErrors !== []) {
            $mergedComm = Communication::fromArray(array_merge($communication->toArray(), $commData));
            $mergedContent = CommunicationContent::fromArray(array_merge($existingContent->toArray(), $contentData));
            $components = [$tool->buildForm($mergedComm, $mergedContent, null, null, $fieldErrors)];
        } else {
            $communications->update($id, $commData);

            if ($existingContent->id !== null) {
                $contents->update($existingContent->id, $contentData);
            } else {
                $contentData['communication_id'] = (string) $id;
                $contents->insert($contentData);
            }

            $components = [$tool->buildForm(
                $communications->find($id),
                $contents->findByCommunicationId($id),
                'Modifiche salvate.',
                'success'
            )];
        }

        return $this->renderPage($components, ['title' => 'Modifica comunicazione']);
    }

    public function deleteAction(): ?string
    {
        $id = (int) $this->param('id');

        $communications = $this->container->get(CommunicationRepository::class);
        $contents = $this->container->get(CommunicationContentRepository::class);

        $communications->delete($id);
        $existingContent = $contents->findByCommunicationId($id);
        if ($existingContent !== null) {
            $contents->delete($existingContent->id);
        }

        $components = $this->container->get(ListCommunicationsTool::class)->execute(['page' => 1]);

        return $this->renderPage($components, ['title' => 'Comunicazioni']);
    }

    /**
     * Stessa logica di App\Controller\AbstractEntityController::collectAndValidate(),
     * qui parametrizzata sull'entity key perche' 'communications' ne ha
     * due (vedi la stessa nota su EditCommunicationTool::fieldsFor() sul
     * perche' non vale la pena generalizzare la base per un solo
     * consumatore in piu').
     *
     * @return array{0: array<string,?string>, 1: array<string,string>}
     */
    private function collectAndValidate(string $entityKey): array
    {
        $fields = (new PackageManifest('communications'))->entities()[$entityKey]['fields'];

        $data = [];
        $fieldErrors = [];
        foreach ($fields as $key => $definition) {
            // communication_id e' la FK che collega le due tabelle,
            // impostata solo da questo controller (vedi newAction/
            // editAction) - non deve mai arrivare da un campo del form,
            // altrimenti un client malevolo potrebbe reintestare un
            // contenuto a una comunicazione diversa passando un valore a
            // mano nella richiesta.
            if ($key === 'communication_id') {
                continue;
            }

            $raw = $this->request->get($key, null);
            if ($raw === null) {
                continue;
            }

            $value = trim((string) $raw);
            $data[$key] = $value !== '' ? $value : null;

            if ($value === '') {
                if (($definition['base'] ?? false) && !($definition['nullable'] ?? false)) {
                    $fieldErrors[$key] = ($definition['label'] ?? $key) . " e' obbligatorio.";
                }
                continue;
            }

            if (isset($definition['format'])) {
                $error = FieldValidator::validate($definition['format'], $value, $definition['formatOptions'] ?? []);
                if ($error !== null) {
                    $fieldErrors[$key] = $error;
                }
            }
        }

        return [$data, $fieldErrors];
    }
}
