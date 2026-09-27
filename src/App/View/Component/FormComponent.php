<?php

declare(strict_types=1);

namespace App\View\Component;

use App\Core\Csrf;

/**
 * Form di modifica: campi inviati via fetch() (vedi
 * public/js/components/form.js) verso 'action', ririsposto dal server
 * con lo stesso formato JSON di qualunque richiesta AJAX. Config attesa:
 *  - title, action, method ('POST' di default)
 *  - fields: [{key, label, value, type, required, width}] - piatto, il
 *    valore vero di ogni campo; 'width' e' un suggerimento di layout
 *    ('quarter'|'third'|'half'|'full', default 'half') per il renderer.
 *  - layout: opzionale, struttura sezioni(tab)/box che raggruppa i
 *    campi per KEY - vedi App\Package\PackageManifest::layout(). Se
 *    assente, tutti i campi finiscono in un'unica sezione/box implicita
 *    (stesso comportamento di prima - Utenti/Permessi non ne hanno una).
 *  - submitLabel, secondaryActions, message/messageType
 *
 * Il lavoro di incrocio fatto qui (non nel Tool, non nel client): un box
 * che nel manifest elenca 4 campi ma per questo progetto ne ha solo 3
 * installati mostra solo quei 3, non lascia un buco; un box i cui campi
 * non sono TUTTI disponibili sparisce del tutto invece di apparire
 * vuoto - il problema concreto osservato nel sistema box/section/tab di
 * Core (vedi la nota architetturale in App\Package\PackageManifest),
 * dove va gestito a mano con una classe CSS "hidden" per ogni caso.
 */
final class FormComponent extends AbstractComponent
{
    private const WIDTHS = ['quarter', 'third', 'half', 'full'];

    public function toData(array $config): array
    {
        $fields = $config['fields'] ?? [];
        $fieldsByKey = [];
        foreach ($fields as $field) {
            $field['width'] = in_array($field['width'] ?? null, self::WIDTHS, true) ? $field['width'] : 'half';
            $fieldsByKey[$field['key']] = $field;
        }

        $sections = $this->resolveSections($config['layout'] ?? null, $fieldsByKey, $fields);

        return [
            'type' => 'form',
            // Stesso motivo di DataTableComponent::entity - vedi li'.
            'entity' => $config['entity'] ?? null,
            // Stesso motivo di DataTableComponent::url - qui di default
            // coincide con 'action' (la form E' la pagina di quella
            // risorsa), quasi mai serve passarlo esplicito.
            'url' => $config['url'] ?? $config['action'],
            'title' => $config['title'] ?? '',
            'action' => $config['action'],
            'method' => $config['method'] ?? 'POST',
            'sections' => $sections,
            'submitLabel' => $config['submitLabel'] ?? 'Salva',
            'secondaryActions' => $config['secondaryActions'] ?? [],
            'message' => $config['message'] ?? null,
            'messageType' => $config['messageType'] ?? null,
            'csrfToken' => Csrf::token(),
        ];
    }

    /**
     * @param array<string, array> $fieldsByKey
     * @param array $allFields ordine originale, usato solo per il fallback
     */
    private function resolveSections(?array $layout, array $fieldsByKey, array $allFields): array
    {
        if ($layout === null || empty($layout['sections'])) {
            return $this->implicitSection($allFields);
        }

        $sections = [];
        foreach ($layout['sections'] as $section) {
            $boxes = [];
            foreach ($section['boxes'] ?? [] as $box) {
                $resolved = array_values(array_filter(array_map(
                    static fn (string $key) => $fieldsByKey[$key] ?? null,
                    $box['fields'] ?? []
                )));

                if ($resolved === []) {
                    continue;
                }

                $boxes[] = [
                    'title' => $box['title'] ?? null,
                    'area' => $box['area'] ?? 'main',
                    'fields' => $resolved,
                ];
            }

            if ($boxes === []) {
                continue;
            }

            $sections[] = ['label' => $section['label'] ?? null, 'boxes' => $boxes];
        }

        return $sections !== [] ? $sections : $this->implicitSection($allFields);
    }

    private function implicitSection(array $allFields): array
    {
        if ($allFields === []) {
            return [];
        }

        return [[
            'label' => null,
            'boxes' => [['title' => null, 'area' => 'main', 'fields' => $allFields]],
        ]];
    }
}
