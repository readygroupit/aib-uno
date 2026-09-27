<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router a segmenti, ispirato a Laminas\Router\Http\Segment ma senza il resto
 * dello stack: route dichiarate esplicitamente in config, ":param" per i
 * segmenti obbligatori, "[...]" per le parti opzionali.
 */
final class Router
{
    public function __construct(private readonly array $routes)
    {
    }

    public function match(string $path): ?array
    {
        foreach ($this->routes as $route) {
            $pattern = $this->compile($route['path']);
            if (preg_match($pattern, $path, $matches) === 1) {
                $params = array_filter(
                    $matches,
                    static fn ($key) => !is_int($key),
                    ARRAY_FILTER_USE_KEY
                );

                return [
                    'controller' => $route['controller'],
                    'action' => $route['action'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    private function compile(string $path): string
    {
        $pattern = preg_replace('#\[([^\]]+)\]#', '(?:$1)?', $path);
        $pattern = preg_replace('#:([a-zA-Z_][a-zA-Z0-9_]*)#', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $pattern . '$#';
    }
}
