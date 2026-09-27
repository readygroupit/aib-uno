<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;

/**
 * Registra ogni query SQL (DbService::query(), unico punto di passaggio)
 * e ogni errore non gestito (Application::run()), suddivisi per
 * Controller e per Repository che li ha generati - a spunto dal logger
 * query di Core (vedi /var/www/core), che li' si e' rivelato utile
 * proprio per risalire a "cos'e' successo" quando arriva una
 * segnalazione, senza dover riprodurre il bug a mano. Una riga JSON per
 * evento: leggibile a occhio, parsabile a macchina se un giorno serve.
 *
 * setRequestContext() viene chiamato una volta sola da Application::run()
 * appena risolta la rotta, prima di eseguire l'action: da li' in poi ogni
 * query sa quale controller/azione l'ha generata senza doverlo passare
 * esplicitamente ad ogni chiamata a DbService (il Container mantiene
 * questa istanza come singleton per tutta la richiesta).
 */
final class LoggerService
{
    private ?string $controllerClass = null;
    private ?string $action = null;

    public function __construct(private readonly Container $container)
    {
    }

    public function setRequestContext(string $controllerClass, string $action): void
    {
        $this->controllerClass = $controllerClass;
        $this->action = $action;
    }

    public function logQuery(string $sql, ?string $error, ?string $repositoryClass, ?string $repositoryFunction): void
    {
        $payload = [
            'time' => date('H:i:s'),
            'type' => 'sql',
            'query' => $sql,
            'error' => $error,
            'controller' => $this->controllerClass,
            'action' => $this->action,
            'repositoryClass' => $repositoryClass,
            'repositoryFunction' => $repositoryFunction,
        ];

        $this->write('_all', $payload);

        if ($this->controllerClass !== null) {
            $this->write('controller/' . $this->shortName($this->controllerClass), $payload);
        }

        if ($repositoryClass !== null) {
            $this->write('repository/' . $this->shortName($repositoryClass), $payload);
        }
    }

    public function logError(\Throwable $e): void
    {
        $this->write('_all', [
            'time' => date('H:i:s'),
            'type' => 'error',
            'message' => $e->getMessage(),
            'exception' => get_class($e),
            'location' => $e->getFile() . ':' . $e->getLine(),
            'controller' => $this->controllerClass,
            'action' => $this->action,
        ]);

        $this->write('error', [
            'time' => date('H:i:s'),
            'trace' => (string) $e,
        ]);
    }

    private function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    private function write(string $relativeName, array $payload): void
    {
        $file = ROOT_PATH . '/data/logs/' . date('Y-m-d') . '/' . $relativeName . '.log';
        $dir = dirname($file);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $file,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
}
