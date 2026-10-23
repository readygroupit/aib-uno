<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private function __construct(
        private readonly string $method,
        private readonly string $pathInfo,
        private readonly array $query,
        private readonly array $post,
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $_POST,
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPathInfo(): string
    {
        return $this->pathInfo;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->post[$name] ?? $this->query[$name] ?? $default;
    }

    /** File caricato da un form multipart (null se assente o con errore). */
    public function file(string $name): ?array
    {
        $file = $_FILES[$name] ?? null;

        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK ? $file : null;
    }

    /** Indirizzo da cui si sta usando l'app (per i link da mandare fuori: pagamenti, area cliente). */
    public function baseUrl(): string
    {
        $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    /** Il corpo grezzo (webhook: la firma si calcola su questo, non su $_POST). */
    public function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    public function header(string $name): ?string
    {
        return $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] ?? null;
    }

    public function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Per le richieste con corpo JSON (es. /prompt) il token CSRF non puo'
     * viaggiare in un campo POST - $_POST resta vuoto, PHP lo popola solo
     * per form-urlencoded/multipart. Vedi Csrf/window.UNO_CSRF.
     */
    public function csrfHeader(): ?string
    {
        return $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
}
