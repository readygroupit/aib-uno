<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Model\AbstractModel;
use App\Model\WorkOrder;
use App\Model\WorkOrderMilestone;
use App\Repository\WorkOrderMilestoneRepository;
use App\Repository\WorkOrderRepository;
use App\Work\ProcessService;

/**
 * Fissare una data chiave del progetto. Se il processo prevede un'email al
 * cliente, il form la mostra gia' compilata (cliente, squadra, firma): chi
 * fissa la data la rilegge e la cambia prima che parta.
 */
final class EditWorkOrderMilestoneTool extends AbstractProcessChildTool
{
    protected function repositoryClass(): string
    {
        return WorkOrderMilestoneRepository::class;
    }

    protected function manifestEntityKey(): string
    {
        return 'work_order_milestones';
    }

    protected function entityTag(): string
    {
        return 'work_order_milestone';
    }

    protected function baseUrl(): string
    {
        return '/commesse/date';
    }

    protected function formTitle(bool $isNew): string
    {
        return $isNew ? 'Nuova data chiave' : 'Fissa la data';
    }

    protected function hiddenFields(): array
    {
        return ['work_order_id'];
    }

    protected function systemManagedFields(): array
    {
        return ['client_email_sent_at', 'appointment_id', 'milestone_id'];
    }

    protected function parentPage(AbstractModel $model, string $message): ?array
    {
        $order = $this->container->get(WorkOrderRepository::class)->find((int) $model->workOrderId);

        return $order !== null ? $this->container->get(EditWorkOrderTool::class)->buildPage($order, $message, 'success') : null;
    }

    protected function fieldsFromManifest(AbstractModel $model, array $fieldErrors = []): array
    {
        $fields = parent::fieldsFromManifest($model, $fieldErrors);
        if (!$model instanceof WorkOrderMilestone) {
            return $fields;
        }

        // Gia' partita: si vede cosa e' stato mandato, non si rimanda.
        if ($model->clientEmailSentAt !== null) {
            return array_values(array_filter(
                array_map(static function (array $f) use ($model) {
                    if ($f['key'] === 'send_client_email') {
                        $f['help'] = 'Email gia\' mandata il ' . date('d/m/Y H:i', strtotime((string) $model->clientEmailSentAt)) . '.';
                        $f['inputType'] = 'hidden';
                        $f['value'] = '0';
                    }

                    return $f;
                }, $fields),
                static fn (array $f) => !in_array($f['key'], ['client_email_to'], true)
            ));
        }

        $order = $this->container->get(WorkOrderRepository::class)->find((int) $model->workOrderId);
        if (!$order instanceof WorkOrder || $fieldErrors !== []) {
            return $fields;
        }
        $email = $this->container->get(ProcessService::class)->renderEmail(
            $order,
            (string) $model->clientEmailSubject,
            (string) $model->clientEmailBody,
            $model->scheduledAt
        );
        foreach ($fields as &$field) {
            if ($field['key'] === 'client_email_subject') {
                $field['value'] = $email['subject'];
            } elseif ($field['key'] === 'client_email_body') {
                $field['value'] = $email['body'];
                $field['help'] = "{data} diventa la data scelta qui sopra. Parte salvando, una volta sola.";
            } elseif ($field['key'] === 'label') {
                $field['inputType'] = 'hidden';
            } elseif ($field['key'] === 'client_email_to' && trim((string) ($field['value'] ?? '')) === '') {
                // Di solito l'email del cliente (dalla sua scheda o dal referente che decide).
                $field['value'] = (string) ($this->container->get(ProcessService::class)->customerEmails($order) ?? '');
                $field['help'] = $field['value'] !== ''
                    ? "L'email del cliente, dalla sua scheda: cambiala se serve. Piu' indirizzi separati da virgola."
                    : "Il cliente non ha un'email nella sua scheda: scrivila qui, oppure spegni l'invio.";
            }
        }

        return $fields;
    }

    public function name(): string
    {
        return 'edit_work_order_milestone';
    }

    public function description(): string
    {
        return "Fissa una data chiave di un progetto (briefing, presentazioni), con l'eventuale email al cliente.";
    }
}
