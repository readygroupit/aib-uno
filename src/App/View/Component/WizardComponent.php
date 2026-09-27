<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Dati per il modale "cosa posso fare?" (vedi public/js/wizard.js): due
 * elenchi, mai testo scritto qui - li costruisce ShowWizardTool a
 * partire dal PromptToolRegistry (funzioni permesse) e dal manifest del
 * pacchetto dell'entita' in vista (campi che il manifest marca come
 * bisognosi di spiegazione).
 */
final class WizardComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'wizard',
            'title' => $config['title'] ?? 'Cosa posso fare?',
            'capabilities' => $config['capabilities'] ?? [],
            'fieldHelp' => $config['fieldHelp'] ?? [],
        ];
    }
}
