<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AbstractEntityController;
use App\Model\AbstractModel;
use App\Prompt\Tool\ListCustomersTool;
use App\Prompt\Tool\EditCustomerNoteTool;
use App\Repository\CustomerNoteRepository;

final class CustomerNotesController extends AbstractEntityController
{
    protected function repositoryClass(): string
    {
        return CustomerNoteRepository::class;
    }

    protected function editToolClass(): string
    {
        return EditCustomerNoteTool::class;
    }

    protected function listToolClass(): string
    {
        return ListCustomersTool::class;
    }

    protected function packageName(): string
    {
        return 'client_knowledge';
    }

    protected function manifestEntityKey(): string
    {
        return 'customer_notes';
    }

    protected function listPageTitle(): string
    {
        return 'Clienti';
    }

    protected function newPageTitle(): string
    {
        return 'Nel diario del cliente';
    }

    protected function editPageTitle(): string
    {
        return 'Nel diario del cliente';
    }

    protected function defaultsOnCreate(): array
    {
        return ['kind' => 'nota', 'noted_at' => date('Y-m-d'), 'source' => 'a mano', 'on_roadmap' => '0', 'author_user_id' => (string) $this->container->get(\App\Service\AuthService::class)->currentUserId()];
    }

    protected function createdMessage(): string
    {
        return 'Salvato nel diario del cliente.';
    }

    /** Chi e' menzionato viene avvisato; se e' nella roadmap anche la squadra (la roadmap e' condivisa con chi ci lavora). */
    protected function afterCreate(int $id): void
    {
        $note = $this->repository()->find($id);
        if ($note instanceof \App\Model\CustomerNote) {
            $this->container->get(\App\Knowledge\KnowledgeService::class)->notifyNote($id, (int) $note->onRoadmap === 1);
        }
    }

    /** Solo chi e' appena stato aggiunto (o la squadra, se la nota e' appena entrata nella roadmap). */
    protected function afterUpdate(int $id, AbstractModel $before): ?string
    {
        $note = $this->repository()->find($id);
        if (!$note instanceof \App\Model\CustomerNote || !$before instanceof \App\Model\CustomerNote) {
            return null;
        }
        $old = array_filter(explode(',', (string) $before->mentionedUserIds));
        $new = array_diff(array_filter(explode(',', (string) $note->mentionedUserIds)), $old);
        $toRoadmap = (int) $note->onRoadmap === 1 && (int) $before->onRoadmap !== 1;
        if ($new !== [] || $toRoadmap) {
            $this->container->get(\App\Knowledge\KnowledgeService::class)->notifyNote($id, $toRoadmap, array_map('intval', array_values($new)));
        }

        return null;
    }
}
