<?php

declare(strict_types=1);

namespace App\Event;

/**
 * Contratto di un listener registrato su un evento (vedi EventDispatcher).
 * Come JobHandlerInterface: nessuna eccezione deve propagarsi fuori da
 * qui - il dispatcher la intercetta comunque, ma un listener che gestisce
 * i propri errori resta piu' leggibile nel log.
 */
interface EventListenerInterface
{
    public function handle(string $eventName, array $payload): void;
}
