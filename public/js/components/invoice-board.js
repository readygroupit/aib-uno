import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, actionButton } from '../accounts-ui.js';

// Fatture per cosa c'e' da fare: prima cio' che va fatturato o inviato,
// poi cio' che si aspetta di incassare. Ogni riga ha il passo successivo.

export function invoiceRow(item) {
    return el('div', { className: `acc-due acc-due--${item.tone}` }, [
        el('div', { className: `acc-due__date acc-due__date--${item.tone}` }, [el('span', { className: 'acc-due__day' }, [item.date])]),
        el('div', { className: 'acc-due__text' }, [
            el('a', { className: 'acc-due__customer', href: item.customerHref }, [item.customer]),
            el('a', { className: 'acc-due__desc', href: item.href }, [item.number ? `${item.number} · ${item.description}` : item.description]),
            el('span', { className: 'acc-due__meta' }, [item.when]),
        ]),
        el('span', { className: 'acc-due__amount' }, [item.amount]),
        el('div', { className: 'acc-due__actions' }, [
            item.sdi ? badge(item.sdi, item.sdiTone) : null,
            item.action ? actionButton(item.action.label, item.action.action, { iconName: item.action.icon, variant: 'secondary' }) : null,
            item.secondAction ? actionButton(item.secondAction.label, item.secondAction.action, { variant: 'ghost' }) : null,
        ].filter(Boolean)),
    ]);
}

function group(g) {
    return el('section', { className: `card acc-section acc-group acc-group--${g.tone}` }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [g.title]),
            el('span', { className: 'acc-group__total' }, [`${g.items.length}`]),
        ]),
        g.hint ? el('p', { className: 'acc-muted inv-hint' }, [g.hint]) : null,
        el('div', { className: 'acc-due-list' }, g.items.map(invoiceRow)),
    ]);
}

function renderInvoiceBoard(data) {
    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, [data.title]),
        el('div', { className: 'claim-table__actions' }, (data.links || []).map((l) => el('a', { className: `btn btn--${l.variant || 'secondary'}`, href: l.href }, [l.label]))),
    ]);
    const main = data.groups.length
        ? data.groups.map(group)
        : [el('section', { className: 'card acc-section' }, [el('p', { className: 'acc-empty' }, [icon('circle-check'), ' Niente da fatturare ne\' da inviare.'])])];

    return el('div', { className: 'acc-board' }, [
        header,
        el('div', { className: 'stat-grid' }, data.stats.map(renderComponent).filter(Boolean)),
        el('div', { className: 'acc-board__layout' }, [
            el('div', { className: 'acc-board__main' }, main),
            el('div', { className: 'acc-board__side' }, [
                el('section', { className: 'card acc-section' }, [
                    el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Pagate di recente'])]),
                    data.recentPaid.length
                        ? el('div', { className: 'acc-due-list' }, data.recentPaid.map(invoiceRow))
                        : el('p', { className: 'acc-empty' }, ['Nessuna fattura pagata ancora.']),
                ]),
            ]),
        ]),
    ]);
}

registerComponent('invoice-board', renderInvoiceBoard);
