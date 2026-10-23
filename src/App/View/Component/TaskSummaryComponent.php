<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Testata di una singola attivita': a cosa appartiene, quando, chi, checklist, fatta/riapri.
 */
final class TaskSummaryComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'task-summary'] + $config;
    }
}
