import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { dateJump } from '../date-jump.js';
import { timelineRow } from './day-plan.js';

// La settimana di una persona ("la scaletta di Sara la prossima
// settimana"): la scaletta di ogni giorno, una sotto l'altra, con il carico.
function renderWeekPlan(data) {
    const nav = el('div', { className: 'cal-nav' }, [
        el('a', { className: 'cal-nav__btn', href: data.nav.prev, title: 'Settimana prima', 'aria-label': 'Settimana prima' }, [icon('chevron-left')]),
        el('a', { className: 'cal-nav__today' + (data.nav.isCurrent ? ' is-current' : ''), href: data.nav.current }, ['Questa settimana']),
        el('a', { className: 'cal-nav__btn', href: data.nav.next, title: 'Settimana dopo', 'aria-label': 'Settimana dopo' }, [icon('chevron-right')]),
        dateJump((date) => data.nav.jump + date, { title: 'Vai alla settimana di un giorno' }),
    ]);

    const header = el('div', { className: 'claim-table__header' }, [
        el('div', { className: 'cal-header__titles' }, [
            el('h1', { className: 'claim-table__title' }, [data.title]),
            el('span', { className: 'cal-header__week' }, [`${data.weekLabel} · ${data.summary}`]),
        ]),
        el('div', { className: 'claim-table__actions' }, [
            nav,
            el('a', { className: 'btn btn--secondary', href: data.nav.today }, ['La giornata di oggi']),
        ]),
    ]);

    const days = data.days.map((d) => el('section', { className: 'card acc-section' + (d.isToday ? ' wl-today' : '') }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [el('a', { href: d.href }, [d.label])]),
            el('div', { className: 'wk-task__chips' }, [
                d.working ? el('span', { className: 'wk-chip' + (d.loadTone === 'success' ? '' : ` wk-chip--${d.loadTone}`), title: 'Giornata pianificabile gia\' occupata' }, [`${d.load}%`]) : null,
                d.overflowLabel ? el('span', { className: 'wk-chip wk-chip--danger' }, [d.overflowLabel]) : null,
                d.absence ? el('span', { className: 'wk-chip wk-chip--warning' }, [d.absence]) : null,
            ].filter(Boolean)),
        ]),
        d.rows.length
            ? el('div', { className: 'pl-timeline' }, d.rows.map(timelineRow))
            : el('p', { className: 'acc-empty' }, [d.working ? 'Niente in programma.' : (d.absence ? `${d.absence}: niente in programma.` : 'Giorno di riposo.')]),
    ]));

    return el('div', { className: 'pl' }, [
        header,
        ...(days.length ? days : [el('section', { className: 'card' }, [el('p', { className: 'acc-empty' }, ['Nessun giorno di lavoro in questa settimana.'])])]),
    ]);
}

registerComponent('week-plan', renderWeekPlan);
