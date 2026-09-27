<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Container DI minimale: nessuna configurazione di factory obbligatoria.
 *
 * - get(Foo::class) senza una factory registrata istanzia "new Foo($container)"
 *   (autowiring lazy, stesso trucco della vecchia AbstractFactory ma senza dover
 *   generare/registrare nulla in config).
 * - set() serve solo per i pochi casi che hanno bisogno di una costruzione
 *   diversa (valori scalari, config, singleton particolari).
 */
final class Container
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }

        if (class_exists($id)) {
            return $this->instances[$id] = new $id($this);
        }

        throw new \RuntimeException("Impossibile risolvere il servizio \"$id\"");
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || isset($this->factories[$id])
            || class_exists($id);
    }
}
