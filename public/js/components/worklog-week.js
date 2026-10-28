import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { dateJump } from '../date-jump.js';

// Il proprio registro: la settimana giorno per giorno, con il totale.
function renderWeek(data) {
    const header = el('div', { className: 'claim-table__header' }, [
        el('div', { className: 'cal-header__titles' }, [
            el('h1', { className: 'claim-table__title' }, [data.title]),
            el('span', { className: 'cal-header__week' }, [`${data.weekLabel} · ${data.total}`]),
        ]),
        el('div', { className: 'claim-table__actions' }, [
            el('div', { className: 'cal-nav' }, [
                el('a', { className: 'cal-nav__btn', href: data.prev, 'aria-label': 'Settimana prima' }, [icon('chevron-left')]),
                el('a', { className: 'cal-nav__today', href: '/registro' }, ['Oggi']),
                el('a', { className: 'cal-nav__btn', href: data.next, 'aria-label': 'Settimana dopo' }, [icon('chevron-right')]),
                dateJump((date) => `/registro?settimana=${date}`, { title: 'Vai alla settimana di un giorno', value: data.monday || '' }),
            ]),
            data.insightsHref ? el('a', { className: 'btn btn--secondary', href: data.insightsHref }, ['Tempi e andamento']) : null,
            el('a', { className: 'btn btn--secondary', href: data.addHref }, [icon('plus'), 'Aggiungi a mano']),
            el('a', { className: 'btn btn--primary', href: data.checkinHref }, [data.checkinLabel]),
        ]),
    ]);

    const days = data.days.filter((d) => !d.future || d.entries.length).map((d) => el('section', { className: 'card acc-section' + (d.isToday ? ' wl-today' : '') }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [d.label]),
            el('span', { className: 'acc-group__total' }, [d.total ? el('strong', {}, [d.total]) : 'niente segnato']),
        ]),
        d.entries.length
            ? el('div', { className: 'acc-due-list' }, d.entries.map((e) => el('a', { className: 'acc-line', href: e.href }, [
                el('span', { className: 'acc-line__title' }, [e.title]),
                el('span', { className: 'acc-line__meta' }, [[e.customer, e.source === 'manuale' ? 'aggiunta a mano' : null].filter(Boolean).join(' · '), el('strong', {}, [e.duration])]),
            ])))
            : el('p', { className: 'acc-empty' }, [d.isToday ? 'Oggi non hai ancora segnato niente.' : 'Nessuna voce.']),
    ]));

    return el('div', { className: 'pl' }, [header, ...days]);
}

registerComponent('worklog-week', renderWeek);
