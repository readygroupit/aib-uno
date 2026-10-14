<?php

declare(strict_types=1);

namespace Index\Controller;

use App\Connector\ConnectorRegistry;
use App\Controller\AuthController;
use App\Import\SheetImporter;
use App\Import\XlsxReader;
use App\Prompt\Tool\ShowImportTool;

/** /importazione: pagina e avvio dell'importazione dal foglio (vedi SheetImporter). */
final class ImportController extends AuthController
{
    protected ?string $requiredPermission = 'users.manage';

    public function indexAction(): ?string
    {
        return $this->renderPage($this->container->get(ShowImportTool::class)->execute([]), ['title' => 'Importa dati']);
    }

    public function runAction(): void
    {
        if ($this->request->getMethod() !== 'POST') {
            $this->json(['ok' => false, 'error' => 'Metodo non consentito.'], 405);

            return;
        }

        try {
            $file = $this->request->file('file');
            if ($file !== null) {
                $tabs = XlsxReader::read((string) $file['tmp_name']);
                $source = 'file ' . (string) $file['name'];
            } else {
                $tabs = $this->container->get(ConnectorRegistry::class)->get('google_sheets')->readAllTabs();
                $source = 'foglio Google collegato';
            }
            $result = $this->container->get(SheetImporter::class)->import($tabs, (string) $this->request->get('removeDemo', '') === '1');
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);

            return;
        }

        $this->json(['ok' => true, 'source' => $source] + $result);
    }
}
