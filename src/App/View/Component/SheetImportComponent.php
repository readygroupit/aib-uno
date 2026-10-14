<?php

declare(strict_types=1);

namespace App\View\Component;

use App\Connector\ConnectorRegistry;

/** Pagina "Importa dati": foglio Google collegato o file Excel, una volta sola o quando serve (sheet-import.js). */
final class SheetImportComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        $connector = null;
        try {
            $connector = $this->container->get(ConnectorRegistry::class)->get('google_sheets');
        } catch (\Throwable) {
        }

        return [
            'type' => 'sheet-import',
            'url' => '/importazione',
            'connected' => $connector !== null && $connector->isConnected(),
            'sheet' => $connector !== null && $connector->isConnected() ? $connector->summary() : null,
        ];
    }
}
