<?php

declare(strict_types=1);

namespace App\Provisioning;

/** Messaggio scritto per l'operatore + i passaggi fatti prima dell'errore. */
final class ProvisioningException extends \RuntimeException
{
    /** @param string[] $log */
    public function __construct(string $message, public readonly array $log = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
