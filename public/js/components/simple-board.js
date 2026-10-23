import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';

// Schermata a sezioni di righe, per le cose piccole (ferie, spese): in
// cima cio' che aspetta una decisione, poi il resto.
function row(r) {
    return el('div', { className: 'acc-due acc-due--nodate' }, [
        el('div', { className: 'acc-due__text' }, [
            el('a', { className: 'acc-due__desc', href: r.href }, [r.title]),
            r.meta ? el('span', { className: 'acc-due__meta' }, [r.meta]) : null,
        ]),
        r.amount ? el('span', { className: 'acc-due__amount' }, [r.amount]) : null,
        el('div', { className: 'acc-due__actions' }, [
            r.badge ? badge(r.badge.label, r.badge.tone) : null,
            r.actions && r.actions.length ? actionsRow(r.actions) : null,
        ].filter(Boolean)),
    ]);
}

function renderSimpleBoard(data) {
    return el('div', { className: 'acc-board' }, [
        el('div', { className: 'claim-table__header' }, [
            el('h1', { className: 'claim-table__title' }, [data.title]),
            el('div', { className: 'claim-table__actions' }, [
                data.createHref ? el('a', { className: 'btn btn--primary', href: data.createHref }, [icon('plus'), data.createLabel]) : null,
            ]),
        ]),
        data.stats.length ? el('div', { className: 'stat-grid' }, data.stats.map(renderComponent).filter(Boolean)) : null,
        ...data.sections.map((s) => el('section', { className: `card acc-section acc-group acc-group--${s.tone || 'neutral'}` }, [
            el('div', { className: 'acc-section__head' }, [
                el('h2', { className: 'card__title' }, [s.title]),
                s.total ? el('span', { className: 'acc-group__total' }, [s.total]) : null,
            ]),
            s.rows.length ? el('div', { className: 'acc-due-list' }, s.rows.map(row)) : el('p', { className: 'acc-empty' }, [s.empty || 'Niente.']),
        ])),
    ].filter(Boolean));
}

registerComponent('simple-board', renderSimpleBoard);
