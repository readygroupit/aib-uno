<?php

declare(strict_types=1);

namespace App\Model;

/** Modalita' prova: un'azione che sarebbe uscita davvero (vedi TestModeService). */
final class SimulatedAction extends AbstractModel
{
    public ?string $effect = null;
    public ?string $summary = null;
    public ?string $detail = null;
    public ?string $recipient = null;
    public ?string $entityType = null;
    public ?int $entityId = null;
}
