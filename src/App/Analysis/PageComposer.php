<?php

declare(strict_types=1);

namespace App\Analysis;

use App\Core\Container;
use App\View\Component\BreakdownComponent;
use App\View\Component\ChartComponent;
use App\View\Component\DashboardHeaderComponent;
use App\View\Component\DataTableComponent;
use App\View\Component\InsightComponent;
use App\View\Component\PanelComponent;
use App\View\Component\StatBoxComponent;

/**
 * Traduce i blocchi scelti dall'IA (vedi AnalysisService::COMPOSE_RULES) nei
 * componenti veri dell'interfaccia, controllando tutto: tipi ammessi, lunghezze,
 * numero di elementi, una sola card protagonista. Un blocco non valido si
 * scarta, non rompe la pagina.
 */
final class PageComposer
{
    private const DOT_COLORS = ['petrol', 'teal', 'moss', 'lilac', 'brass', 'danger'];
    private const TONES = ['success', 'warning', 'danger', 'info', 'neutral'];

    public function __construct(private readonly Container $container)
    {
    }

    /** @return list<array> dati dei componenti, nell'ordine di lettura */
    public function compose(array $blocks): array
    {
        $components = [];
        $featureUsed = false;
        foreach (array_slice($blocks, 0, 10) as $block) {
            if (!is_array($block)) {
                continue;
            }
            foreach ($this->block($block, $featureUsed, true) as $component) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /** @return list<array> */
    private function block(array $block, bool &$featureUsed, bool $topLevel): array
    {
        try {
            return match ((string) ($block['type'] ?? '')) {
                'header' => $topLevel ? [$this->container->get(DashboardHeaderComponent::class)->toData([
                    'dateLabel' => mb_strtoupper(self::text($block['eyebrow'] ?? '', 80)),
                    'greeting' => self::text($block['title'] ?? '', 90),
                ])] : [],
                'kpis' => $this->kpis((array) ($block['items'] ?? []), $featureUsed),
                'chart' => [$this->container->get(ChartComponent::class)->toData([
                    'title' => self::text($block['title'] ?? '', 90),
                    'subtitle' => self::nullableText($block['subtitle'] ?? null, 140),
                    'mode' => $block['mode'] ?? 'rows',
                    'unit' => self::text($block['unit'] ?? '', 6),
                    'categories' => array_map(static fn ($c) => self::text($c, 40), (array) ($block['categories'] ?? [])),
                    'series' => array_map(static fn ($s) => ['name' => self::text($s['name'] ?? '', 30), 'values' => (array) ($s['values'] ?? [])], array_filter((array) ($block['series'] ?? []), 'is_array')),
                    'highlight' => $block['highlight'] ?? null,
                ])],
                'breakdown' => [$this->breakdown($block)],
                'table' => [$this->table($block)],
                'insight' => [$this->container->get(InsightComponent::class)->toData([
                    'title' => self::text($block['title'] ?? 'In sintesi', 60),
                    'points' => array_map(static fn ($p) => self::text($p, 220), (array) ($block['points'] ?? [])),
                ])],
                'group' => $topLevel ? $this->group($block, $featureUsed) : [],
                default => [],
            };
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<array> */
    private function kpis(array $items, bool &$featureUsed): array
    {
        $items = array_values(array_filter(array_slice($items, 0, 6), 'is_array'));
        // Il dato n.1 e' sempre in evidenza: se l'IA non l'ha indicato, il primo.
        $wanted = array_search(true, array_map(static fn ($i) => !empty($i['feature']), $items), true);
        if ($wanted === false && !$featureUsed && count($items) > 1) {
            $wanted = 0;
        }
        $statBox = $this->container->get(StatBoxComponent::class);
        $result = [];
        foreach ($items as $index => $item) {
            $feature = !$featureUsed && $wanted === $index;
            $featureUsed = $featureUsed || $feature;
            $trend = array_values(array_filter((array) ($item['trend'] ?? []), 'is_numeric'));
            $result[] = $statBox->toData([
                'label' => self::text($item['label'] ?? '', 40),
                'value' => self::text($item['value'] ?? '', 24),
                'comparison' => self::nullableText($item['note'] ?? null, 70),
                'badge' => self::nullableText($item['badge'] ?? null, 28),
                'feature' => $feature,
                'dotColor' => in_array($item['color'] ?? '', self::DOT_COLORS, true) ? $item['color'] : self::DOT_COLORS[$index % 5],
                'sparkline' => count($trend) >= 2 ? array_map('floatval', array_slice($trend, -12)) : null,
                'sparklineStyle' => 'bars',
            ]);
        }

        return $result;
    }

    private function breakdown(array $block): array
    {
        $groups = [];
        foreach (array_slice(array_filter((array) ($block['groups'] ?? [$block]), 'is_array'), 0, 3) as $group) {
            $counts = [];
            foreach (array_slice((array) ($group['counts'] ?? []), 0, 10, true) as $label => $value) {
                if (is_array($value)) {
                    $label = $value['label'] ?? $label;
                    $value = $value['value'] ?? 0;
                }
                $counts[self::text($label, 40)] = (int) round((float) $value);
            }
            $highlight = is_array($group['highlight'] ?? null) && !empty($group['highlight']['text'])
                ? ['text' => self::text($group['highlight']['text'], 40), 'tone' => in_array($group['highlight']['tone'] ?? '', self::TONES, true) ? $group['highlight']['tone'] : 'info']
                : null;
            $tones = [];
            foreach ((array) ($group['tones'] ?? []) as $label => $tone) {
                if (in_array($tone, self::TONES, true)) {
                    $tones[self::text($label, 40)] = $tone;
                }
            }
            $groups[] = ['title' => self::text($group['title'] ?? '', 60), 'counts' => $counts, 'highlight' => $highlight, 'tones' => $tones];
        }

        return $this->container->get(BreakdownComponent::class)->toData([
            'title' => self::nullableText($block['title'] ?? null, 90),
            'subtitle' => self::nullableText($block['subtitle'] ?? null, 140),
            'groups' => $groups,
        ]);
    }

    private function table(array $block): array
    {
        $columns = [];
        foreach (array_slice(array_filter((array) ($block['columns'] ?? []), 'is_array'), 0, 6) as $column) {
            $key = (string) preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($column['key'] ?? '')));
            if ($key !== '') {
                $columns[] = ['key' => $key, 'label' => self::text($column['label'] ?? $key, 30)];
            }
        }
        $keys = array_column($columns, 'key');
        $rows = [];
        foreach (array_slice(array_filter((array) ($block['rows'] ?? []), 'is_array'), 0, 30) as $row) {
            $clean = [];
            foreach ($keys as $key) {
                $clean[$key] = self::text($row[$key] ?? '', 80);
            }
            $rows[] = $clean;
        }

        return $this->container->get(DataTableComponent::class)->toData([
            'title' => self::text($block['title'] ?? '', 90),
            'columns' => $columns,
            'rows' => $rows,
            'emptyMessage' => 'Nessun dato.',
        ]);
    }

    /** @return list<array> */
    private function group(array $block, bool &$featureUsed): array
    {
        $children = [];
        foreach (array_slice(array_filter((array) ($block['blocks'] ?? []), 'is_array'), 0, 4) as $child) {
            foreach ($this->block($child, $featureUsed, false) as $component) {
                $children[] = $component;
            }
        }
        // Un gruppo con un solo elemento sarebbe un titolo sopra un altro titolo.
        if (count($children) <= 1) {
            return $children;
        }

        return [$this->container->get(PanelComponent::class)->toData([
            'eyebrow' => self::nullableText($block['eyebrow'] ?? null, 60),
            'title' => self::text($block['title'] ?? '', 90),
            'subtitle' => self::nullableText($block['subtitle'] ?? null, 140),
            'children' => $children,
        ])];
    }

    private static function text(mixed $value, int $max): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }

    private static function nullableText(mixed $value, int $max): ?string
    {
        $text = self::text($value, $max);

        return $text === '' ? null : $text;
    }
}
