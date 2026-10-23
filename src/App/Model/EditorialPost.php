<?php

declare(strict_types=1);

namespace App\Model;

final class EditorialPost extends AbstractModel
{
    public ?int $customerId = null;
    public ?string $channel = null;
    public ?string $format = null;
    public ?string $publishDate = null;
    public ?string $publishTime = null;
    public ?string $title = null;
    public ?string $caption = null;
    public ?string $mediaNotes = null;
    public ?string $stage = null;
    public ?int $assignedUserId = null;
    public ?string $clientFeedback = null;
    public ?string $approvedAt = null;
    public ?string $publishedUrl = null;
    public ?int $taskId = null;
    public ?int $writerUserId = null;
    public ?string $sourceLink = null;
    public ?string $outputLink = null;
    public ?int $parentPostId = null;
    public ?string $reviewNote = null;
    public ?int $internalApprovedBy = null;
    public ?string $internalApprovedAt = null;
    public ?string $approvedVia = null;
    public ?int $revisionCount = null;
    public ?int $writerTaskId = null;

    /** Ancora da fare (non pubblicato, non scartato). */
    public function isOpen(): bool
    {
        return !in_array($this->stage, ['pubblicato', 'scartato'], true);
    }
}
