<?php

declare(strict_types=1);

namespace App\Model;

/** Chiave/valore del progetto (tabella settings, vedi FirstAccessService). */
final class Setting extends AbstractModel
{
    public ?string $settingKey = null;
    public ?string $value = null;
}
