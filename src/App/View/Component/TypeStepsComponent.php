<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Le fasi di un tipo di commessa, in ordine, con giorno previsto, tempo e attese del cliente.
 */
final class TypeStepsComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'type-steps'] + $config;
    }
}
