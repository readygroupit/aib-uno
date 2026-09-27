<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Allegato polimorfico: entity+entityId puntano alla riga proprietaria
 * (es. entity="User", entityId=42) di un'altra tabella qualsiasi.
 */
final class Attachment extends AbstractModel
{
    public ?string $entity = null;
    public ?int $entityId = null;
    public ?string $type = null;
    public ?string $name = null;
    public ?string $path = null;
    public ?string $mimeType = null;
    public ?int $size = null;
}
