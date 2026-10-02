<?php

declare(strict_types=1);

namespace App\View\Component;

use App\Provisioning\ProjectProvisioner;
use App\Repository\ProjectRepository;
use App\Service\AuthService;
use App\Support\Sections;

/** I progetti generati da Uno e il modulo per crearne uno nuovo (vedi projects-board.js). */
final class ProjectsBoardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        $provisioner = $this->container->get(ProjectProvisioner::class);
        $presets = $provisioner->presets();

        $projects = array_map(static fn ($project) => [
            'name' => $project->name,
            'slug' => $project->slug,
            'url' => $project->url,
            'preset' => $presets[$project->preset]['label'] ?? null,
            'packages' => json_decode((string) $project->packages, true) ?: [],
            'withDemoData' => (bool) $project->withDemoData,
            'createdAt' => $project->createdAt,
        ], $this->container->get(ProjectRepository::class)->findAll([], 'id DESC'));

        $packages = array_map(static fn (array $package) => $package + [
            'categoryLabel' => Sections::label($package['category']) ?? ucfirst((string) $package['category']),
        ], $provisioner->selectablePackages());

        return [
            'type' => 'projects-board',
            'projects' => $projects,
            'presets' => array_values(array_map(static fn (array $preset) => [
                'key' => $preset['key'],
                'label' => $preset['label'],
                'description' => $preset['description'],
                'appName' => $preset['appName'] ?? null,
                'packages' => $preset['packages'],
                'hasDemo' => $preset['hasDemo'],
            ], $presets)),
            'packages' => $packages,
            'canCreate' => $this->container->get(AuthService::class)->hasPermission('provisioning.create'),
        ];
    }
}
