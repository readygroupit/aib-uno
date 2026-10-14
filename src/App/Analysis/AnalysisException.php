<?php

declare(strict_types=1);

namespace App\Analysis;

/** Richiesta di analisi non valida o non eseguibile (campo sconosciuto, operatore non ammesso...). */
final class AnalysisException extends \RuntimeException
{
}
