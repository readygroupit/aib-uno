<?php

declare(strict_types=1);

namespace App\View\Component;

/**
 * Grafico a barre per confronti e andamenti (chart.js): 'rows' = una riga
 * per categoria con una barra per serie (es. utenti x anni), 'columns' =
 * colonne nel tempo (es. mesi). La serie 'highlight' (di solito la piu'
 * recente) e' nel petrolio pieno, le altre nei toni chiari del tema.
 */
final class ChartComponent extends AbstractComponent
{
    public function toData(array $config): array
    {
        $categories = array_values(array_map('strval', array_slice((array) ($config['categories'] ?? []), 0, 24)));
        $series = [];
        foreach (array_slice((array) ($config['series'] ?? []), 0, 4) as $item) {
            $values = array_map(static fn ($v) => is_numeric($v) ? (float) $v : 0.0, array_slice((array) ($item['values'] ?? []), 0, count($categories)));
            $series[] = ['name' => (string) ($item['name'] ?? ''), 'values' => array_pad($values, count($categories), 0.0)];
        }
        // "non indicato" (valore mancante) sempre in fondo, qualunque ordine arrivi.
        if (($config['mode'] ?? 'rows') !== 'columns') {
            $order = array_keys($categories);
            usort($order, static fn (int $a, int $b) => [self::isMissing($categories[$a]), $a] <=> [self::isMissing($categories[$b]), $b]);
            $categories = array_map(static fn (int $i) => $categories[$i], $order);
            foreach ($series as &$item) {
                $item['values'] = array_map(static fn (int $i) => $item['values'][$i], $order);
            }
            unset($item);
        }
        $highlight = isset($config['highlight']) ? (int) $config['highlight'] : count($series) - 1;

        return [
            'type' => 'chart',
            'title' => (string) ($config['title'] ?? ''),
            'subtitle' => $config['subtitle'] ?? null,
            'mode' => ($config['mode'] ?? 'rows') === 'columns' ? 'columns' : 'rows',
            'unit' => (string) ($config['unit'] ?? ''),
            'categories' => $categories,
            'series' => $series,
            'highlight' => max(0, min(count($series) - 1, $highlight)),
        ];
    }

    private static function isMissing(string $label): bool
    {
        return in_array(mb_strtolower(trim($label)), ['non indicato', 'non indicata', 'n/d', 'nessuno', 'senza compagnia'], true);
    }
}
