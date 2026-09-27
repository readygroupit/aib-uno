<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Una riga = un destinatario da spedire per una campagna - vedi
 * packages/campaigns/package.php per il perche' non ha ancora una
 * verticale propria (lista/form), solo Model+Repository per uso
 * programmatico (es. un futuro Job che processa la coda).
 */
final class CampaignEmail extends AbstractModel
{
    public ?int $campaignId = null;
    public ?string $recipientEmail = null;
    public ?int $leadId = null;
    public ?string $stage = null;
    public ?string $sentAt = null;
    public ?string $errorMessage = null;
}
