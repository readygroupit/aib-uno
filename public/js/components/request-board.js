import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, shortList } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';

// Richieste dei clienti: prima quelle da smistare, poi quelle in corso,
// poi gli extra fatti da mettere in conto. Ogni riga ha il suo passo.

function requestRow(r) {
    return el('div', { className: `acc-due acc-due--nodate acc-due--${r.tone}` }, [
        el('div', { className: 'acc-due__text' }, [
            el('a', { className: 'acc-due__customer', href: r.customerHref }, [r.customer]),
            el('a', { className: 'acc-due__desc', href: r.href }, [r.summary]),
            el('span', { className: 'acc-due__meta' }, [r.meta]),
        ]),
        el('div', { className: 'acc-due__actions' }, [
            r.extra ? badge(r.extra, 'warning', 'coins') : null,
            r.who ? badge(r.who, 'neutral', 'user') : null,
            r.priority === 'alta' ? badge('urgente', 'danger') : null,
            actionsRow(r.actions),
        ].filter(Boolean)),
    ]);
}

function renderRequestBoard(data) {
    return el('div', { className: 'acc-board' }, [
        el('div', { className: 'claim-table__header' }, [
            el('h1', { className: 'claim-table__title' }, [data.title]),
            el('div', { className: 'claim-table__actions' }, [
                data.createHref ? el('a', { className: 'btn btn--primary', href: data.createHref }, [icon('plus'), 'Segna una richiesta']) : null,
            ]),
        ]),
        el('div', { className: 'stat-grid' }, data.stats.map(renderComponent).filter(Boolean)),
        el('div', { className: 'acc-board__layout' }, [
            el('div', { className: 'acc-board__main' }, data.groups.length
                ? data.groups.map((g) => el('section', { className: `card acc-section acc-group acc-group--${g.tone}` }, [
                    el('div', { className: 'acc-section__head' }, [
                        el('h2', { className: 'card__title' }, [g.title, el('span', { className: 'acc-count' }, [String(g.items.length)])]),
                    ]),
                    shortList(g.items.map(requestRow)),
                ]))
                : [el('section', { className: 'card acc-section' }, [el('p', { className: 'acc-empty' }, [icon('circle-check'), ' Nessuna richiesta aperta.'])])]),
            el('div', { className: 'acc-board__side' }, [
                data.top.length ? el('section', { className: 'card acc-section' }, [
                    el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Chi chiede di piu\' (30 giorni)'])]),
                    shortList(data.top.map((t) => el('a', { className: 'acc-line', href: t.href }, [
                        el('span', { className: 'acc-line__title' }, [t.name]),
                        el('span', { className: 'acc-line__meta' }, [`${t.count} ${t.count === 1 ? 'richiesta' : 'richieste'}`]),
                    ]))),
                ]) : null,
                data.done.length ? el('section', { className: 'card acc-section' }, [
                    el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Fatte questa settimana', el('span', { className: 'acc-count' }, [String(data.done.length)])])]),
                    shortList(data.done.map(requestRow)),
                ]) : null,
            ].filter(Boolean)),
        ]),
    ]);
}

registerComponent('request-board', renderRequestBoard);
