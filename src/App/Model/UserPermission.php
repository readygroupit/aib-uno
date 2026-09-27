<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Override diretto su singolo utente: 'grant' aggiunge un permesso che il
 * profilo non darebbe, 'revoke' toglie un permesso che il profilo darebbe.
 */
final class UserPermission extends AbstractModel
{
    public ?int $userId = null;
    public ?int $permissionId = null;
    public ?string $effect = null;
}
