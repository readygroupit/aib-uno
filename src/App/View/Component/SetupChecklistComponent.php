<?php

declare(strict_types=1);

namespace App\View\Component;

use App\Service\SetupService;

final class SetupChecklistComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return ['type' => 'setup-checklist'] + $this->container->get(SetupService::class)->payload();
    }
}
