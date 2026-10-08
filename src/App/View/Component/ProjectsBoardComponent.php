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

        $projects = array_map(fn ($project) => [
            'name' => $project->name,
            'slug' => $project->slug,
            'url' => $provisioner->projectUrl((string) $project->slug),
            // Link di primo accesso (slug cifrato): utile finche' il progetto
            // non e' configurato, poi il progetto lo ignora.
            'firstAccessUrl' => $provisioner->firstAccessUrl((string) $project->slug),
            'provisioningStatus' => $project->provisioningStatus ?? 'ready',
            'provisioningLog' => $project->provisioningLog,
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
            // Aperta dal prompt, la barra degli indirizzi passa a /progetti (vedi extractUrl() in hero.js).
            'url' => '/progetti',
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
            // Per l'anteprima dell'indirizzo nel modulo ("Sara' raggiungibile su ...").
            'hostPattern' => preg_replace('#^https?://#', '', $provisioner->projectUrl('%s')),
            'canCreate' => $this->container->get(AuthService::class)->hasPermission('provisioning.create'),
        ];
    }
}
