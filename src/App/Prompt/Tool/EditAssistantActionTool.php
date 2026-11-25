<?php

declare(strict_types=1);

namespace App\Prompt\Tool;

use App\Core\Container;
use App\Model\AbstractModel;
use App\Model\AssistantAction;
use App\Repository\AssistantActionRepository;
use App\Repository\WorkOrderRepository;

/**
 * Un messaggio preparato dall'assistente (un sollecito al cliente): si
 * rilegge, si cambia se serve e si manda con lo stesso pulsante. Dopo,
 * si torna alla propria giornata.
 */
final class EditAssistantActionTool extends AbstractEditEntityTool
{
    private Container $container;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->container = $container;
    }

    protected function repositoryClass(): string
    {
        return AssistantActionRepository::class;
    }

    protected function packageName(): string
    {
        return 'assistant';
    }

    protected function manifestEntityKey(): string
    {
        return 'assistant_actions';
    }

    protected function entityTag(): string
    {
        return 'assistant_action';
    }

    protected function baseUrl(): string
    {
        return '/assistente/azioni';
    }

    protected function formTitle(bool $isNew): string
    {
        return 'Messaggio per il cliente';
    }

    protected function searchColumns(): array
    {
        return ['title'];
    }

    protected function candidateColumns(): array
    {
        return [['key' => 'title', 'label' => 'Cosa']];
    }

    public function menuLabel(): ?string
    {
        return null;
    }

    public function buildPage(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        if ($model instanceof AssistantAction && $model->stage !== 'proposta') {
            $page = $this->container->get(ShowAssistantTool::class)->execute([]);
            array_unshift($page, ['type' => 'notice', 'tone' => $messageType === 'error' ? 'error' : 'success', 'text' => $message ?? 'Gia\' deciso.']);

            return $page;
        }

        return parent::buildPage($model, $message, $messageType, $fieldErrors);
    }

    public function buildForm(AbstractModel $model, ?string $message = null, ?string $messageType = null, array $fieldErrors = []): array
    {
        $form = parent::buildForm($model, $message, $messageType, $fieldErrors);
        $form['submitLabel'] = 'Manda al cliente';
        $form['collapsed'] = false;

        return $form;
    }

    protected function detailComponents(AbstractModel $model): array
    {
        if (!$model instanceof AssistantAction) {
            return [];
        }
        $order = $model->entityType === 'work_orders' && $model->entityId !== null
            ? $this->container->get(WorkOrderRepository::class)->find((int) $model->entityId)
            : null;

        return [[
            'type' => 'notice',
            'tone' => 'info',
            'text' => trim((string) $model->title . ($order !== null ? " \u{2013} {$order->title}" : '') . '. ' . (string) $model->body
                . ' Rileggi e cambia quello che vuoi: parte con "Manda al cliente".'),
        ]];
    }

    protected function formTitleBelowDetail(): ?string
    {
        return 'Messaggio per il cliente';
    }

    public function name(): string
    {
        return 'edit_assistant_action';
    }

    public function description(): string
    {
        return "Apre un messaggio preparato dall'assistente per un cliente, da rileggere e mandare.";
    }

    public function requiredPermission(): ?string
    {
        return null;
    }
}
