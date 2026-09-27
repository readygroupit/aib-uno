<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Una riga di configurazione. `type` (vedi ConfigType) dice come
 * interpretare `value`. Se `type` e' COMBO, le opzioni vengono lette dal
 * campo `options` (JSON) quando presente, altrimenti da un provider
 * registrato in codice per quella `key` (comportamento del vecchio core).
 * `dependsOnConfigId`/`dependsOnValue`: questa riga va mostrata in UI solo
 * se la config puntata ha quel valore (es. i campi di Klarna dipendono
 * dallo switch "klarna abilitato").
 */
final class Config extends AbstractModel
{
    public ?int $configGroupId = null;
    public ?string $key = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?int $type = null;
    public ?string $value = null;
    public ?string $options = null;
    public ?int $dependsOnConfigId = null;
    public ?string $dependsOnValue = null;
    public int $sortOrder = 0;
}
