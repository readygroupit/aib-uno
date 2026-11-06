<?php

declare(strict_types=1);

namespace App\Model;

/** Una pagina Facebook o un profilo Instagram trovato con MetaConnector. */
final class SocialAccount extends AbstractModel
{
    public ?string $network = null;
    public ?string $externalId = null;
    public ?string $name = null;
    public ?string $username = null;
    public ?string $pageId = null;
    public ?string $accessToken = null;
    public ?int $customerId = null;

    /** "Instagram @pizzeria" o "Facebook Pizzeria Gino". */
    public function label(): string
    {
        return $this->network === 'instagram'
            ? 'Instagram ' . ($this->username ? '@' . $this->username : (string) $this->name)
            : 'Facebook ' . $this->name;
    }
}
