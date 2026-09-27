<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Core\Container;
use Memcached;

final class MemcachedService
{
    private readonly Memcached $memcached;
    private readonly string $prefix;

    public function __construct(Container $container)
    {
        $config = $container->get(Config::class);

        $this->memcached = new Memcached();
        $this->memcached->addServer(
            (string) $config->get('memcached.host', '127.0.0.1'),
            (int) $config->get('memcached.port', 11211)
        );
        $this->prefix = (string) $config->get('memcached.prefix', '');
    }

    public function get(string $key): mixed
    {
        $value = $this->memcached->get($this->prefix . $key);

        return $this->memcached->getResultCode() === Memcached::RES_SUCCESS ? $value : null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->memcached->set($this->prefix . $key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->memcached->delete($this->prefix . $key);
    }

    /**
     * Legge dalla cache, e se assente esegue $callback e ne salva il risultato.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}
