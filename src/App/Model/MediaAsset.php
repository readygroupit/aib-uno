<?php

declare(strict_types=1);

namespace App\Model;

final class MediaAsset extends AbstractModel
{
    public ?string $source = null;
    public ?string $externalId = null;
    public ?string $name = null;
    public ?string $kind = null;
    public ?string $mimeType = null;
    public ?string $folder = null;
    public ?int $customerId = null;
    public ?string $webLink = null;
    public ?int $sizeBytes = null;
    public ?int $width = null;
    public ?int $height = null;
    public ?int $durationSeconds = null;
    public ?string $modifiedAt = null;
    public ?string $analysisStatus = null;
    public ?string $analysisError = null;
    public ?string $description = null;
    public ?string $tags = null;
    public ?int $peopleCount = null;
    public ?string $setting = null;
    public ?string $occasion = null;
    public ?string $orientation = null;
    public ?string $analysis = null;
    public ?string $thumbPath = null;
    public ?string $analyzedAt = null;

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }

    /** L'analisi dell'IA come array (vuoto se non c'e'). */
    public function analysisData(): array
    {
        $data = $this->analysis !== null ? json_decode($this->analysis, true) : null;

        return is_array($data) ? $data : [];
    }
}
