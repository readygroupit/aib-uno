<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Ripartizioni per stato affiancate (es. pratiche, documenti, rimborsi del
 * report): per ognuna una barra unica a segmenti e le righe con barra,
 * conteggio e percentuale - al posto delle tabelle "stato / conteggio" una
 * sotto l'altra. Il colore di ogni stato lo decide qui tone() (verde = fatto,
 * giallo = in corso, rosso = problema), il disegno breakdown.js.
 */
final class BreakdownComponent extends AbstractComponent
{
    private const TONES = [
        'success' => ['completo', 'pagato', 'rimborsato', 'chiusa', 'conclusa', 'accordo', 'convertito', 'inviata', 'consegnata', 'letta', 'si', 'sì'],
        'danger' => ['mancante', 'non pagato', 'respinta', 'respinto', 'perso', 'fallita', 'scaduto', 'no'],
        'warning' => ['parziale', 'in conciliazione', 'in attesa', 'sospes', 'da richiamare', 'raccolta'],
        'info' => ['reclamo', 'inviato', 'contattato', 'qualificato', 'proposta', 'aperta', 'avviata', 'nuovo'],
    ];

    /**
     * @param array{title?: string, subtitle?: string, groups: list<array{title: string, href?: string, counts: array<string,int>, highlight?: ?array{text: string, tone: string}}>} $config
     */
    public function toData(array $config): array
    {
        $groups = [];
        foreach ($config['groups'] ?? [] as $group) {
            $counts = $group['counts'] ?? [];
            arsort($counts);
            $total = array_sum($counts);
            $items = [];
            foreach ($counts as $label => $count) {
                $items[] = [
                    'label' => (string) $label,
                    'count' => (int) $count,
                    'percent' => $total > 0 ? (int) round($count * 100 / $total) : 0,
                    'tone' => self::tone((string) $label),
                ];
            }
            $groups[] = [
                'title' => $group['title'] ?? '',
                'href' => $group['href'] ?? null,
                // Il dato chiave del riquadro (es. "78% completi"), scelto da chi
                // chiama: e' la prima cosa da leggere dopo il totale.
                'highlight' => $group['highlight'] ?? null,
                'total' => $total,
                'items' => $items,
            ];
        }

        return [
            'type' => 'breakdown',
            'title' => $config['title'] ?? null,
            'subtitle' => $config['subtitle'] ?? null,
            'legend' => $config['legend'] ?? [['tone' => 'success', 'label' => 'Fatto'], ['tone' => 'warning', 'label' => 'In corso'], ['tone' => 'danger', 'label' => 'Da sbloccare']],
            'groups' => $groups,
        ];
    }

    private static function tone(string $label): string
    {
        $label = mb_strtolower(trim($label));
        // Le frasi negative prima: "non pagato" non deve finire in "pagato".
        foreach (['danger', 'warning', 'success', 'info'] as $tone) {
            foreach (self::TONES[$tone] as $word) {
                if ($label === $word || (mb_strlen($word) > 2 && str_contains($label, $word))) {
                    return $tone;
                }
            }
        }

        return 'neutral';
    }
}
