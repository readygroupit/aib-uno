<?php

declare(strict_types=1);

namespace App\Connector;

use App\Core\Container;

/**
 * Registro codice-connettore -> istanza, stesso principio di
 * PromptToolRegistry/JobHandlerRegistry (registrazione esplicita in
 * config/connectors.php, auto-wiring via Container).
 */
final class ConnectorRegistry
{
    /** @var array<string, ConnectorInterface> */
    private array $connectors = [];

    public function __construct(private readonly Container $container)
    {
    }

    public function register(string $connectorClass): void
    {
        /** @var ConnectorInterface $connector */
        $connector = $this->container->get($connectorClass);
        $this->connectors[$connector->code()] = $connector;
    }

    public function get(string $code): ConnectorInterface
    {
        if (!isset($this->connectors[$code])) {
            throw new \RuntimeException("Nessun connettore registrato con codice '{$code}'");
        }

        return $this->connectors[$code];
    }

    /** @return ConnectorInterface[] */
    public function all(): array
    {
        return array_values($this->connectors);
    }
}
