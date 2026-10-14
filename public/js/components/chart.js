import { registerComponent } from './registry.js';
import { el } from '../dom.js';

// Grafico a barre (vedi ChartComponent): la serie in evidenza nel petrolio
// pieno, le altre nei toni chiari del tema.
const SOFT = ['#9fbab6', '#e5c979', '#b9b3d1', '#c9d4d2'];

function seriesColor(index, highlight) {
    if (index === highlight) return '#12333f';
    const others = [...Array(4).keys()].filter((i) => i !== highlight);
    return SOFT[others.indexOf(index) % SOFT.length];
}

export function formatNumber(value, unit) {
    const money = unit === '€';
    const text = new Intl.NumberFormat('it-IT', { maximumFractionDigits: money ? 2 : 1, minimumFractionDigits: money && value % 1 ? 2 : 0 }).format(value);
    if (!unit) return text;
    return unit === '%' ? `${text}%` : `${text} ${unit}`;
}

function legend(data) {
    if (data.series.length < 2) return null;
    return el('div', { className: 'chart__legend' }, data.series.map((s, i) => el('span', {}, [
        el('span', { className: 'chart__swatch', style: `background:${seriesColor(i, data.highlight)}` }),
        s.name,
    ])));
}

function renderRows(data, max) {
    return el('div', { className: 'chart__rows' }, data.categories.map((category, c) => el('div', { className: 'chart__row' }, [
        el('span', { className: 'chart__row-label', title: category }, [category]),
        el('div', { className: 'chart__row-bars' }, data.series.map((s, i) => el('div', { className: 'chart__row-bar' + (i === data.highlight ? ' is-main' : '') }, [
            el('span', { className: 'chart__track' }, [
                el('span', { className: 'chart__fill', style: `width:${s.values[c] > 0 ? Math.max(2, (s.values[c] / max) * 100) : 0}%;background:${seriesColor(i, data.highlight)}` }),
            ]),
            el('span', { className: 'chart__value' }, [formatNumber(s.values[c], data.unit)]),
        ]))),
    ])));
}

function renderColumns(data, max) {
    return el('div', { className: 'chart__columns' }, data.categories.map((category, c) => el('div', { className: 'chart__column' }, [
        el('div', { className: 'chart__column-bars' }, data.series.map((s, i) => el('span', {
            className: 'chart__col' + (i === data.highlight ? ' is-main' : ''),
            style: `height:${s.values[c] > 0 ? Math.max(3, (s.values[c] / max) * 100) : 0}%;background:${seriesColor(i, data.highlight)}`,
            title: `${s.name ? s.name + ' · ' : ''}${category}: ${formatNumber(s.values[c], data.unit)}`,
        }, i === data.highlight && s.values[c] > 0 ? [el('em', {}, [formatNumber(s.values[c], data.unit)])] : []))),
        el('span', { className: 'chart__column-label', title: category }, [category]),
    ])));
}

function renderChart(data) {
    const max = Math.max(1, ...data.series.flatMap((s) => s.values));
    return el('section', { className: 'card chart' }, [
        el('div', { className: 'chart__head' }, [
            el('div', {}, [
                el('h2', { className: 'chart__title' }, [data.title]),
                data.subtitle ? el('p', { className: 'chart__subtitle' }, [data.subtitle]) : null,
            ].filter(Boolean)),
            legend(data),
        ].filter(Boolean)),
        data.categories.length === 0
            ? el('p', { className: 'chart__empty' }, ['Nessun dato da mostrare.'])
            : (data.mode === 'columns' ? renderColumns(data, max) : renderRows(data, max)),
    ]);
}

registerComponent('chart', renderChart);
