<?php

declare(strict_types=1);

namespace App\Event;

use App\Core\Container;

/**
 * Registro nome-evento -> listener, stesso principio di App\Job\JobHandlerRegistry
 * (registrazione esplicita, niente scansione automatica). Un listener puo'
 * essere una classe (risolta via Container::get(), stesso auto-wiring di
 * ogni altra classe del framework) o una closure - la closure serve
 * apposta per i casi in cui la configurazione del listener e' specifica
 * del progetto (es. "quando arriva un lead, usa l'agente X") senza dover
 * scrivere una classe dedicata per ogni combinazione evento->agente.
 *
 * Un listener che fallisce non deve mai far fallire l'operazione che ha
 * generato l'evento (un insert non deve rompersi perche' un effetto
 * collaterale e' andato male): l'eccezione viene intercettata e loggata,
 * non propagata. Nessun LoggerService dedicato esiste ancora (nota gia'
 * in Fase 2), quindi si logga su data/logs/events.log - stesso pattern
 * gia' usato da MailService per data/logs/mail.log.
 */
final class EventDispatcher
{
    /** @var array<string, array<string|callable>> */
    private array $listeners = [];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param string|callable(string,array):void $listener classe che implementa
     *   EventListenerInterface, o una closure(string $eventName, array $payload): void
     */
    public function listen(string $eventName, string|callable $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(string $eventName, array $payload): void
    {
        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            try {
                if (is_string($listener)) {
                    $this->container->get($listener)->handle($eventName, $payload);
                } else {
                    $listener($eventName, $payload);
                }
            } catch (\Throwable $e) {
                $this->logFailure($eventName, $listener, $e);
            }
        }
    }

    private function logFailure(string $eventName, string|callable $listener, \Throwable $e): void
    {
        $listenerName = is_string($listener) ? $listener : 'closure';
        $line = sprintf(
            "[%s] evento '%s', listener '%s': %s\n",
            date('Y-m-d H:i:s'),
            $eventName,
            $listenerName,
            $e->getMessage()
        );

        @file_put_contents(ROOT_PATH . '/data/logs/events.log', $line, FILE_APPEND);
    }
}
