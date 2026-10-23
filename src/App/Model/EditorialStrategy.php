<?php

declare(strict_types=1);

namespace App\Model;

final class EditorialStrategy extends AbstractModel
{
    public ?int $customerId = null;
    public ?string $objectives = null;
    public ?string $audience = null;
    public ?string $tone = null;
    public ?string $pillars = null;
    public ?string $dosDonts = null;
    public ?string $notes = null;

    /** @return string[] le rubriche, una per riga */
    public function pillarList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->pillars) ?: [])));
    }
}
