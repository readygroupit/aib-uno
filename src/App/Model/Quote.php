<?php

declare(strict_types=1);

namespace App\Model;

final class Quote extends AbstractModel
{
    public ?int $customerId = null;
    public ?string $title = null;
    public ?string $quoteNumber = null;
    public ?int $numberYear = null;
    public ?int $numberSeq = null;
    public ?string $quoteDate = null;
    public ?string $validUntil = null;
    public ?string $stage = null;
    public ?int $ownerUserId = null;
    public ?string $intro = null;
    public ?string $conditions = null;
    public ?string $paymentTerms = null;
    public ?int $includedRevisions = null;
    public ?string $discountPercent = null;
    public ?string $totalOneOff = null;
    public ?string $totalYearly = null;
    public ?int $approvedBy = null;
    public ?string $approvedAt = null;
    public ?string $sentAt = null;
    public ?string $acceptedAt = null;
    public ?string $acceptedVia = null;
    public ?string $rejectedReason = null;

    /** Ancora modificabile: dopo l'invio un preventivo non si tocca (al massimo se ne fa un altro). */
    public function isEditable(): bool
    {
        return in_array($this->stage, ['bozza', 'da approvare', 'approvato'], true);
    }
}
