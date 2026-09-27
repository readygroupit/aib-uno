<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Tipo di valore di una riga configs.type (TINYINT su db).
 */
enum ConfigType: int
{
    case STRING = 1;
    case INTEGER = 2;
    case NUMERIC = 3;
    case SWITCH = 4;
    case COMBO = 5;
}
