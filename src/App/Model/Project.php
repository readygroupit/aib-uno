<?php

declare(strict_types=1);

namespace App\Model;

final class Project extends AbstractModel
{
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $dbName = null;
    public ?string $path = null;
    public ?string $url = null;
    public ?string $preset = null;
    public ?string $packages = null;
    public ?int $withDemoData = null;
    /** queued (in coda, produzione) | running | ready | failed - vedi ProjectProvisioner */
    public ?string $provisioningStatus = null;
    public ?string $provisioningLog = null;
}
