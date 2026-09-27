<?php

declare(strict_types=1);

namespace App\Model;

final class ConnectorCredential extends AbstractModel
{
    public ?string $connectorCode = null;
    public ?string $authType = null;
    public ?string $apiKey = null;
    public ?string $accessToken = null;
    public ?string $refreshToken = null;
    public ?string $tokenExpiresAt = null;
    public ?string $extraConfig = null;
}
