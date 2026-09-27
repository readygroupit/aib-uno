<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Nodo di categoria per i permessi. parentId nullo = radice; profondita'
 * libera (una categoria puo' avere sottocategorie che a loro volta ne
 * hanno altre). Serve solo per organizzare la UI quando si assegnano
 * i permessi a un profilo.
 */
final class PermissionGroup extends AbstractModel
{
    public ?int $parentId = null;
    public ?string $code = null;
    public ?string $name = null;
    public ?string $description = null;
    public int $sortOrder = 0;
}
