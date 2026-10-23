import { registerComponent, renderComponent } from './registry.js';
import { el, createSlidingIndicator } from '../dom.js';
import { icon } from '../icons.js';
import { progressBar, initialsBadge } from '../work-ui.js';

// Bacheca delle commesse: una colonna per stato. Ogni scheda dice per chi
// e', a che punto e', cosa viene dopo, quando va consegnata e se c'e' un
// problema (in ritardo, ferma, il cliente non risponde).

function card(c) {
    const flags = [
        c.flags.late ? el('span', { className: 'wk-flag wk-flag--danger' }, ['In ritardo']) : null,
        c.flags.stale ? el('span', { className: 'wk-flag wk-flag--lilac' }, [`Ferma da ${c.idleDays} gg`]) : null,
        c.flags.waiting ? el('span', { className: 'wk-flag wk-flag--warning' }, [c.waitingDays === 0 ? 'Attesa da oggi' : `Attesa da ${c.waitingDays} gg`]) : null,
        c.priority === 'alta' && c.active ? el('span', { className: 'wk-flag wk-flag--danger' }, ['Priorita alta']) : null,
    ].filter(Boolean);

    return el('a', { className: 'wk-card' + (c.active ? '' : ' wk-card--closed'), href: c.href }, [
        el('div', { className: 'wk-card__top' }, [
            el('span', { className: 'wk-card__customer' }, [c.customer]),
            c.type ? el('span', { className: 'wk-card__type' }, [c.type]) : null,
        ]),
        el('span', { className: 'wk-card__title' }, [c.title]),
        flags.length ? el('div', { className: 'wk-card__flags' }, flags) : null,
        progressBar(c.progress),
        c.active && c.next ? el('div', { className: 'wk-card__next' }, [
            icon(c.next.waits ? 'clock' : 'arrow-right'),
            el('span', {}, [c.next.waits ? `Aspettiamo: ${c.next.title}` : `Ora: ${c.next.title}`]),
        ]) : null,
        el('div', { className: 'wk-card__foot' }, [
            c.due ? el('span', { className: `wk-chip wk-chip--${c.due.tone}` }, [icon('calendar'), c.active ? `${c.due.date} · ${c.due.when}` : c.due.when]) : el('span', { className: 'acc-muted' }, ['senza data']),
            initialsBadge(c.ownerInitials, c.owner ? `Responsabile: ${c.owner}` : ''),
        ]),
    ]);
}

const FILTERS = [
    { value: 'tutte', label: 'Tutte', test: () => true },
    { value: 'mie', label: 'Le mie', test: (c, me) => c.ownerId === me },
    { value: 'attesa', label: 'Aspettano il cliente', test: (c) => c.flags.waiting },
    { value: 'ritardo', label: 'In ritardo', test: (c) => c.flags.late },
    { value: 'ferme', label: 'Ferme', test: (c) => c.flags.stale },
];

function renderBoard(data) {
    let active = FILTERS.some((f) => f.value === data.filter) ? data.filter : 'tutte';
    let query = '';
    const all = data.columns.flatMap((col) => col.cards);

    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, [data.title]),
        el('div', { className: 'claim-table__actions' }, [
            data.typesHref ? el('a', { className: 'btn btn--secondary', href: data.typesHref }, ['Tipi di commessa']) : null,
            data.createHref ? el('a', { className: 'btn btn--primary', href: data.createHref }, [data.createLabel || 'Nuova']) : null,
        ]),
    ]);

    const columnsWrap = el('div', { className: 'wk-board' });

    function renderColumns() {
        columnsWrap.textContent = '';
        const filter = FILTERS.find((f) => f.value === active);
        data.columns.forEach((col) => {
            const cards = col.cards.filter((c) => filter.test(c, data.meId) && (!query || `${c.title} ${c.customer} ${c.type || ''}`.toLowerCase().includes(query)));
            columnsWrap.appendChild(el('section', { className: `wk-col wk-col--${col.tone}` }, [
                el('div', { className: 'wk-col__head' }, [
                    el('span', { className: 'wk-col__title' }, [col.title]),
                    el('span', { className: 'wk-col__count' }, [String(cards.length)]),
                ]),
                el('div', { className: 'wk-col__cards' }, cards.length ? cards.map(card) : [el('p', { className: 'wk-col__empty' }, ['Niente qui.'])]),
            ]));
        });
    }

    const tabs = el('div', { className: 'claim-table__tabs' });
    const move = createSlidingIndicator(tabs, 'claim-table__tabs-indicator');
    FILTERS.forEach((f) => {
        const count = all.filter((c) => c.active && f.test(c, data.meId)).length;
        if (count === 0 && f.value !== 'tutte' && f.value !== active) return;
        tabs.appendChild(el('button', {
            type: 'button',
            className: 'claim-table__tab' + (f.value === active ? ' is-active' : ''),
            onClick: (e) => {
                active = f.value;
                tabs.querySelectorAll('.claim-table__tab').forEach((b) => b.classList.remove('is-active'));
                e.currentTarget.classList.add('is-active');
                move(e.currentTarget);
                renderColumns();
            },
        }, [`${f.label} `, el('span', { className: 'claim-table__tab-count' }, [String(f.value === 'tutte' ? all.filter((c) => c.active).length : count)])]));
    });
    requestAnimationFrame(() => move(tabs.querySelector('.is-active')));

    const search = el('input', { type: 'text', placeholder: 'Cerca commessa o cliente...' });
    search.addEventListener('input', () => {
        query = search.value.trim().toLowerCase();
        renderColumns();
    });

    renderColumns();

    return el('div', { className: 'wk' }, [
        header,
        el('div', { className: 'stat-grid' }, (data.stats || []).map(renderComponent).filter(Boolean)),
        el('div', { className: 'wk-toolbar' }, [tabs, el('label', { className: 'claim-table__search' }, [icon('search'), search])]),
        columnsWrap,
    ]);
}

registerComponent('work-order-board', renderBoard);
