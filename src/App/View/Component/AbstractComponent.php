<?php

declare(strict_types=1);

namespace App\View\Component;

use App\Core\Container;

/**
 * Base per i componenti riutilizzabili (tabelle, box statistiche, ecc.).
 * Un componente non produce HTML: produce un array serializzabile in JSON
 * (con un campo 'type' che il renderer JS usa per scegliere come
 * disegnarlo). Il DOM lo costruisce sempre il client — sia per le
 * richieste AJAX (JSON puro) sia per il primo caricamento pagina (JSON
 * incorporato, letto e renderizzato dallo stesso JS all'avvio) — cosi'
 * c'e' un solo motore di rendering, mai due.
 */
abstract class AbstractComponent
{
    public function __construct(protected readonly Container $container)
    {
    }

    abstract public function toData(array $config): array;
}
