<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    public function __construct(private readonly array $data)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->data;
    }
}
