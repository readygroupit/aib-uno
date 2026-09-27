<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Prompt\Tool\ListLeadDuplicatesTool;
use App\Repository\LeadRepository;
use App\View\Component\DataTableComponent;
use App\View\Component\FormComponent;

/**
 * Bespoke: non e' una CRUD su un'entita', e' una schermata di conferma
 * ("colleghiamo questi due?") su una relazione self-FK di 'leads' - vedi
 * LeadRepository::findDuplicateCandidates()/findDuplicateMatch(). GET
 * mostra il confronto, POST scrive SOLO related_lead_id (mai un merge,
 * mai una cancellazione - vedi il brief Assilevi sul perche').
 */
final class LeadDuplicatesController extends AuthController
{
    protected ?string $requiredPermission = 'leads.manage';

    public function indexAction(): ?string
    {
        $components = $this->container->get(ListLeadDuplicatesTool::class)->execute([]);

        return $this->renderPage($components, ['title' => 'Duplicati contatti']);
    }

    public function confirmAction(): ?string
    {
        $id = (int) $this->param('id');
        /** @var LeadRepository $leads */
        $leads = $this->container->get(LeadRepository::class);

        $lead = $leads->find($id);
        $match = $lead !== null ? $leads->findDuplicateMatch($id) : null;

        if ($lead === null || $match === null) {
            /** @var DataTableComponent $dataTable */
            $dataTable = $this->container->get(DataTableComponent::class);
            $components = [$dataTable->toData([
                'title' => 'Duplicati contatti',
                'columns' => [],
                'rows' => [],
                'emptyMessage' => 'Questa coppia non e\' (piu\') un duplicato sospetto.',
            ])];

            return $this->renderPage($components, ['title' => 'Duplicati contatti']);
        }

        if ($this->request->getMethod() === 'POST') {
            $leads->update($id, ['related_lead_id' => (string) $match['aId']]);

            $components = $this->container->get(ListLeadDuplicatesTool::class)->execute([]);

            return $this->renderPage($components, ['title' => 'Duplicati contatti']);
        }

        /** @var FormComponent $form */
        $form = $this->container->get(FormComponent::class);
        $components = [$form->toData([
            'title' => 'Confronta e collega',
            'action' => '/contatti/duplicati/' . $id,
            'submitLabel' => 'Collega come contatti correlati',
            'fields' => [
                [
                    'key' => 'older',
                    'label' => 'Contatto piu\' vecchio (resta quello di riferimento)',
                    'value' => $match['aLabel'] . ' - id #' . $match['aId'],
                    'type' => 'text',
                    'required' => false,
                    'readonly' => true,
                    'width' => 'full',
                ],
                [
                    'key' => 'newer',
                    'label' => 'Contatto piu\' recente (verra\' collegato al primo)',
                    'value' => $match['bLabel'] . ' - id #' . $match['bId'],
                    'type' => 'text',
                    'required' => false,
                    'readonly' => true,
                    'width' => 'full',
                ],
                [
                    'key' => 'reason',
                    'label' => 'Motivo del sospetto',
                    'value' => $match['reason'],
                    'type' => 'text',
                    'required' => false,
                    'readonly' => true,
                    'width' => 'full',
                ],
            ],
        ])];

        return $this->renderPage($components, ['title' => 'Confronta contatti']);
    }
}
