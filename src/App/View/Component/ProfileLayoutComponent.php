<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Griglia a due colonne (2fr/1fr, stesso rapporto di .form-section--split
 * gia' in kit.css) per la pagina profilo - puramente strutturale, i
 * componenti dentro 'main'/'sidebar' sono gia' dati completi di altri
 * Component (FormComponent, PermissionSummaryComponent, StatBoxComponent...),
 * questo li dispone soltanto. Vedi profile-layout.js: chiama
 * renderComponent() (registry.js) su ciascuno, non reinventa il disegno.
 */
final class ProfileLayoutComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        return [
            'type' => 'profile-layout',
            'main' => $config['main'] ?? [],
            'sidebar' => $config['sidebar'] ?? [],
        ];
    }
}
