<?php

declare(strict_types=1);

namespace App\Model;

final class User extends AbstractModel
{
    /** In piu' rispetto ai campi di servizio della base: l'hash non deve mai lasciare il server, nemmeno nel JSON di una lista. */
    protected const HIDDEN_FIELDS = [...parent::HIDDEN_FIELDS, 'passwordHash'];

    public ?int $profileId = null;
    public ?string $username = null;
    public ?string $email = null;
    public ?string $passwordHash = null;
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $lastLoginAt = null;
}
