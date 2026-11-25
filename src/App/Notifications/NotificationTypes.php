<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * I tipi di avviso, con l'etichetta per le preferenze e cosa vale finche'
 * la persona non sceglie (nessuna riga in notification_preferences).
 */
final class NotificationTypes
{
    public const PROJECT_MEMBER_ADDED = 'project_member_added';
    public const TASK_ASSIGNED = 'task_assigned';
    public const STEP_READY = 'step_ready';
    public const APPROVAL_REQUESTED = 'approval_requested';
    public const MILESTONE_SET = 'milestone_set';
    public const CHANGE_REQUEST = 'change_request';
    public const ASSISTANT = 'assistant';
    public const MENTION = 'mention';

    /** @var array<string, array{label:string, help:string, in_app:bool, email:bool}> */
    public const ALL = [
        self::PROJECT_MEMBER_ADDED => ['label' => 'Ti mettono in un progetto', 'help' => 'Sei nella squadra di un progetto nuovo o di uno gia\' avviato.', 'in_app' => true, 'email' => true],
        self::TASK_ASSIGNED => ['label' => 'Ti assegnano un\'attivita\'', 'help' => 'Un\'attivita\' o una fase di un progetto passa a te.', 'in_app' => true, 'email' => false],
        self::STEP_READY => ['label' => 'Tocca a te', 'help' => 'La fase prima della tua e\' fatta: puoi partire.', 'in_app' => true, 'email' => true],
        self::APPROVAL_REQUESTED => ['label' => 'C\'e\' da approvare', 'help' => 'Bozze, preventivi o altro aspettano il tuo ok.', 'in_app' => true, 'email' => true],
        self::MILESTONE_SET => ['label' => 'Fissata una data del progetto', 'help' => 'Briefing, presentazioni e altre date chiave dei tuoi progetti.', 'in_app' => true, 'email' => true],
        self::MENTION => ['label' => 'Ti coinvolgono', 'help' => 'Ti menzionano in un\'idea, una decisione o una nota di un cliente, o la condividono con la squadra.', 'in_app' => true, 'email' => true],
        self::ASSISTANT => ['label' => 'L\'assistente ti ricorda', 'help' => 'Date da fissare, approvazioni che aspettano, clienti da risentire, messaggi pronti da rileggere.', 'in_app' => true, 'email' => true],
        self::CHANGE_REQUEST => ['label' => 'Modifica chiesta dal cliente', 'help' => 'Il cliente chiede una modifica su un tuo progetto.', 'in_app' => true, 'email' => true],
    ];

    public static function label(string $type): string
    {
        return self::ALL[$type]['label'] ?? $type;
    }
}
