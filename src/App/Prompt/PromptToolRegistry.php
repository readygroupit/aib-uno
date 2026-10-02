<?php

declare(strict_types=1);

namespace App\Prompt;

use App\Core\Container;
use App\Package\PackageCatalogService;

/**
 * Registro nome-capacita' -> tool, stesso principio di JobHandlerRegistry
 * ed EventDispatcher (registrazione esplicita, auto-wiring via Container).
 */
final class PromptToolRegistry
{
    /** @var array<string, PromptToolInterface> */
    private array $tools = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Una capacita' che lavora sulle tabelle di qualche pacchetto si
     * registra solo se quei pacchetti sono installati in questo progetto:
     * Uno (configuratore) non mostra "Pratiche", un progetto generato non
     * mostra "Progetti" - stesso codice, menu diverso.
     */
    public function register(string $toolClass, string ...$requiredPackages): void
    {
        if (array_diff($requiredPackages, PackageCatalogService::installedNames()) !== []) {
            return;
        }

        /** @var PromptToolInterface $tool */
        $tool = $this->container->get($toolClass);
        $this->tools[$tool->name()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function get(string $name): PromptToolInterface
    {
        if (!isset($this->tools[$name])) {
            throw new \RuntimeException("Nessuna capacita' registrata con nome '{$name}'");
        }

        return $this->tools[$name];
    }

    /** @return PromptToolInterface[] */
    public function all(): array
    {
        return array_values($this->tools);
    }
}
