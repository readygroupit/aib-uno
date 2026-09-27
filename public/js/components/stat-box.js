import { registerComponent } from './registry.js';
import { el } from '../dom.js';

const SVG_NS = 'http://www.w3.org/2000/svg';

function svgEl(tag, attrs = {}) {
    const node = document.createElementNS(SVG_NS, tag);
    for (const [key, value] of Object.entries(attrs)) {
        node.setAttribute(key, value);
    }
    return node;
}

function buildSparkline(values) {
    if (!values || values.length < 2) {
        return null;
    }

    const width = 180;
    const height = 34;
    const min = Math.min(...values);
    const range = Math.max(...values) - min || 1;
    const step = width / (values.length - 1);

    const points = values.map((value, i) => {
        const x = Math.round(i * step * 10) / 10;
        const y = Math.round((height - ((value - min) / range) * (height - 4) - 2) * 10) / 10;
        return `${x},${y}`;
    });

    const lineStr = points.join(' ');
    const fillStr = `0,${height} ${lineStr} ${width},${height}`;

    const svg = svgEl('svg', { width: '100%', height: String(height), viewBox: `0 0 ${width} ${height}`, preserveAspectRatio: 'none' });
    svg.appendChild(svgEl('polygon', { points: fillStr, fill: 'rgba(18,51,63,0.1)' }));
    svg.appendChild(svgEl('polyline', {
        points: lineStr,
        fill: 'none',
        stroke: '#12333f',
        'stroke-width': '1.6',
        'stroke-linejoin': 'round',
        'stroke-linecap': 'round',
    }));

    return svg;
}

const DOT_COLOR_HEX = { petrol: '#12333f', moss: '#4f7b5c', lilac: '#8b83ad', brass: '#b99333', teal: '#5f8a86', danger: '#8e3a29' };

// Barre invece della linea (vedi 'sparklineStyle: bars' - opt-in, il resto
// dell'app usa ancora la linea di buildSparkline() sopra): l'ultima barra
// nel colore della card (dotColor), le altre in grigio neutro - stesso
// linguaggio delle card viste nel riferimento "Exploration Board".
function buildBarSparkline(values, dotColor) {
    if (!values || values.length === 0) {
        return null;
    }

    const max = Math.max(...values, 1);
    const highlight = DOT_COLOR_HEX[dotColor] || DOT_COLOR_HEX.petrol;

    const bars = values.map((value, i) => {
        const pct = Math.max(8, Math.round((value / max) * 100));
        const isLast = i === values.length - 1;

        return el('span', {
            className: 'stat-box__bar',
            style: `height:${pct}%;background:${isLast ? highlight : 'rgba(18,51,63,0.14)'}`,
        });
    });

    return el('div', { className: 'stat-box__bars' }, bars);
}

function renderStatBox(data) {
    const header = el('div', { className: 'stat-box__header' }, [
        el('span', { className: 'stat-box__label' }, [data.label || '']),
        el('span', { className: 'stat-box__dot stat-box__dot--' + (data.dotColor || 'petrol') }),
    ]);

    const valueChildren = [el('span', { className: 'stat-box__value' }, [String(data.value ?? '')])];
    if (data.delta != null) {
        valueChildren.push(el('span', {
            className: 'stat-box__delta ' + (data.deltaPositive ? 'is-positive' : 'is-negative'),
        }, [data.delta]));
    }

    const className = 'card stat-box' + (data.href ? ' stat-box--clickable' : '');
    const section = el(data.href ? 'a' : 'section', { className, href: data.href || null }, [
        header,
        el('div', { className: 'stat-box__value-row' }, valueChildren),
    ]);

    const spark = data.sparklineStyle === 'bars'
        ? buildBarSparkline(data.sparkline, data.dotColor)
        : buildSparkline(data.sparkline);
    if (spark) {
        section.appendChild(el('div', { className: 'stat-box__spark' }, [spark]));
    }

    if (data.comparison) {
        section.appendChild(el('div', { className: 'stat-box__comparison' }, [data.comparison]));
    }

    return section;
}

registerComponent('stat-box', renderStatBox);
