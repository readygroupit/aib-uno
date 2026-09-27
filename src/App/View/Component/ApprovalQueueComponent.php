<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Coda "human in the loop": condizioni rilevate davvero (contatto fermo,
 * possibile duplicato, documento mancante - vedi ShowHomeDashboardTool),
 * mai un'azione economica/legale/di invio reale in autonomia - coerente
 * col modello a livelli di automazione del brief Assilevi (10 Livelli di
 * automazione): "Approva" esegue solo cio' che e' dichiarato reversibile
 * e onesto (segna contattato, registra un sollecito), "Rivedi" porta
 * sempre alla schermata vera per decidere con calma.
 */
final class ApprovalQueueComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'approval-queue',
            'title' => $config['title'] ?? '',
            'subtitle' => $config['subtitle'] ?? '',
            'emptyMessage' => $config['emptyMessage'] ?? 'Niente da approvare al momento.',
            // [{avatarInitials, avatarColor, tag, tagColor, title, description, agentLabel, reviewHref, approveUrl}]
            'items' => $config['items'] ?? [],
        ];
    }
}
