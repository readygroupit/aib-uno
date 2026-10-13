import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';

const SVG_NS = 'http://www.w3.org/2000/svg';
const TONE_COLORS = { success: '#4f8a64', warning: '#d49a2a', danger: '#c0533f', info: '#4a7f9c', neutral: '#8b83ad' };

function svgEl(tag, attrs) {
    const node = document.createElementNS(SVG_NS, tag);
    Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
    return node;
}

// Anello con il totale al centro: un arco per stato, nel suo colore.
function donut(group) {
    const size = 72;
    const radius = 29;
    const circumference = 2 * Math.PI * radius;
    const gap = group.items.length > 1 ? 3 : 0;
    const svg = svgEl('svg', { width: size, height: size, viewBox: `0 0 ${size} ${size}`, class: 'breakdown__donut-svg' });
    svg.appendChild(svgEl('circle', { cx: size / 2, cy: size / 2, r: radius, fill: 'none', stroke: 'rgba(18,51,63,0.08)', 'stroke-width': 8 }));

    let offset = 0;
    group.items.forEach((item) => {
        const length = group.total > 0 ? (item.count / group.total) * circumference : 0;
        svg.appendChild(svgEl('circle', {
            cx: size / 2,
            cy: size / 2,
            r: radius,
            fill: 'none',
            stroke: TONE_COLORS[item.tone] || TONE_COLORS.neutral,
            'stroke-width': 8,
            'stroke-dasharray': `${Math.max(length - gap, 0.5)} ${circumference}`,
            'stroke-dashoffset': -offset,
            transform: `rotate(-90 ${size / 2} ${size / 2})`,
        }));
        offset += length;
    });

    return el('div', { className: 'breakdown__donut' }, [
        svg,
        el('span', { className: 'breakdown__donut-center' }, [
            el('strong', {}, [String(group.total)]),
            el('span', {}, ['Totale']),
        ]),
    ]);
}

function renderGroup(group) {
    const rows = group.items.map((item) => el('li', { className: 'breakdown__row' }, [
        el('span', { className: 'breakdown__label' }, [
            el('span', { className: `breakdown__dot breakdown--${item.tone}` }),
            el('span', { className: 'breakdown__label-text' }, [item.label]),
        ]),
        el('span', { className: 'breakdown__value' }, [
            el('strong', {}, [String(item.count)]),
            el('span', { className: 'breakdown__percent' }, [`${item.percent}%`]),
        ]),
        el('span', { className: 'breakdown__track' }, [
            el('span', { className: `breakdown__fill breakdown--${item.tone}`, style: `width:${Math.max(item.percent, 2)}%` }),
        ]),
    ]));

    const head = el('div', { className: 'breakdown__head' }, [
        donut(group),
        el('div', { className: 'breakdown__head-text' }, [
            el('h3', { className: 'breakdown__title' }, [group.title]),
            group.highlight ? el('span', { className: `breakdown__chip breakdown__chip--${group.highlight.tone}` }, [group.highlight.text]) : null,
        ].filter(Boolean)),
        group.href ? el('span', { className: 'breakdown__arrow' }, [icon('arrow-right')]) : null,
    ].filter(Boolean));

    return el(group.href ? 'a' : 'div', { className: 'breakdown__group', href: group.href || null }, [
        head,
        group.items.length > 0
            ? el('ul', { className: 'breakdown__rows' }, rows)
            : el('p', { className: 'breakdown__empty' }, ['Nessun dato.']),
    ]);
}

function renderBreakdown(data) {
    const legend = (data.legend || []).length
        ? el('div', { className: 'breakdown__legend' }, data.legend.map((entry) => el('span', {}, [
            el('span', { className: `breakdown__dot breakdown--${entry.tone}` }),
            entry.label,
        ])))
        : null;

    const intro = el('div', { className: 'breakdown__intro' }, [
        el('div', {}, [
            data.title ? el('h2', { className: 'breakdown__heading' }, [data.title]) : null,
            data.subtitle ? el('p', { className: 'breakdown__subtitle' }, [data.subtitle]) : null,
        ].filter(Boolean)),
        legend,
    ].filter(Boolean));

    return el('section', { className: 'card breakdown' }, [
        intro,
        el('div', { className: 'breakdown__grid' }, (data.groups || []).map(renderGroup)),
    ]);
}

registerComponent('breakdown', renderBreakdown);
