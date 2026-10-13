import { registerComponent } from './registry.js';
import { el } from '../dom.js';

// Ripartizioni per stato affiancate (vedi BreakdownComponent): barra unica a
// segmenti in cima, poi una riga per stato con pallino, barra, numero e %.
function renderGroup(group) {
    const stack = el('div', { className: 'breakdown__stack' },
        group.items.map((item) => el('span', {
            className: `breakdown__segment breakdown--${item.tone}`,
            style: `flex-grow:${item.count}`,
            title: `${item.label}: ${item.count}`,
        }))
    );

    const rows = group.items.map((item) => el('li', { className: 'breakdown__row' }, [
        el('span', { className: 'breakdown__label' }, [
            el('span', { className: `breakdown__dot breakdown--${item.tone}` }),
            item.label,
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
        el('h3', { className: 'breakdown__title' }, [group.title]),
        el('span', { className: 'breakdown__total' }, [String(group.total)]),
    ]);

    return el(group.href ? 'a' : 'div', { className: 'breakdown__group', href: group.href || null }, [
        head,
        group.total > 0 ? stack : null,
        group.items.length > 0
            ? el('ul', { className: 'breakdown__rows' }, rows)
            : el('p', { className: 'breakdown__empty' }, ['Nessun dato.']),
    ].filter(Boolean));
}

function renderBreakdown(data) {
    const children = [];
    if (data.title) {
        children.push(el('div', { className: 'breakdown__intro' }, [
            el('h2', { className: 'breakdown__heading' }, [data.title]),
            data.subtitle ? el('p', { className: 'breakdown__subtitle' }, [data.subtitle]) : null,
        ].filter(Boolean)));
    }
    children.push(el('div', { className: 'breakdown__grid' }, (data.groups || []).map(renderGroup)));

    return el('section', { className: 'card breakdown' }, children);
}

registerComponent('breakdown', renderBreakdown);
