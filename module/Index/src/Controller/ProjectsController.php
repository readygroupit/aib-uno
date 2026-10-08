<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Controller\AuthController;
use App\Core\Config;
use App\Prompt\Tool\ShowProjectsTool;
use App\Provisioning\ProjectProvisioner;
use App\Provisioning\ProvisioningException;

/** Progetti generati da Uno: elenco + creazione (vedi ProjectProvisioner). */
final class ProjectsController extends AuthController
{
    protected function requiredPermissionForAction(string $action): ?string
    {
        return $action === 'create' ? 'provisioning.create' : 'provisioning.view';
    }

    public function indexAction(): ?string
    {
        return $this->renderPage($this->container->get(ShowProjectsTool::class)->execute([]), ['title' => 'Progetti']);
    }

    public function createAction(): void
    {
        if ($this->request->getMethod() !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Metodo non consentito.'], 405);

            return;
        }

        $body = json_decode(file_get_contents('php://input') ?: '{}', true);
        $body = is_array($body) ? $body : [];
        $packages = array_values(array_filter((array) ($body['packages'] ?? []), 'is_string'));
        $preset = is_string($body['preset'] ?? null) && $body['preset'] !== '' ? $body['preset'] : null;

        $provisioner = $this->container->get(ProjectProvisioner::class);
        $args = [
            (string) ($body['name'] ?? ''),
            (string) ($body['slug'] ?? ''),
            $packages,
            $preset,
            (bool) ($body['withDemo'] ?? false),
        ];

        try {
            // Sul PC nasce subito; in produzione va in coda e lo crea il
            // cron di root (bin/provision-queue.php) con vhost e certificato.
            if ($this->container->get(Config::class)->get('provisioning')['runInRequest'] ?? false) {
                $result = $provisioner->provision(...$args);
                $project = $result['project'];
                $log = $result['log'];
            } else {
                $project = $provisioner->queue(...$args);
                $log = ["Progetto in coda: entro un paio di minuti saranno pronti codice, database, indirizzo e certificato."];
            }
        } catch (ProvisioningException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage(), 'log' => $e->log], 422);

            return;
        }

        $this->json([
            'ok' => true,
            'queued' => $project->provisioningStatus === 'queued',
            'url' => $provisioner->projectUrl((string) $project->slug),
            'firstAccessUrl' => $provisioner->firstAccessUrl((string) $project->slug),
            'log' => $log,
        ]);
    }
}
