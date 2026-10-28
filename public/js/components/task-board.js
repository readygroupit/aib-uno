import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { taskRow } from '../work-ui.js';

// Attivita' per giorno: prima quello che e' indietro, poi oggi, poi il
// resto. "Le mie" / "Tutte" sono due indirizzi, cosi' il tasto indietro li
// rispetta.
function renderTaskBoard(data) {
    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, [data.title]),
        el('div', { className: 'claim-table__actions' }, [
            el('div', { className: 'wk-switch' }, [
                el('a', { className: 'wk-switch__item' + (data.scope === 'mie' ? ' is-active' : ''), href: '/attivita' }, ['Le mie']),
                el('a', { className: 'wk-switch__item' + (data.scope === 'tutte' ? ' is-active' : ''), href: '/attivita?tutte=1' }, ['Tutte']),
            ]),
            data.createHref ? el('a', { className: 'btn btn--primary', href: data.createHref }, ["Nuova attivita'"]) : null,
        ]),
    ]);

    const groups = data.groups.map((g) => el('section', { className: `card acc-section wk-group wk-group--${g.tone}` }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [g.title]),
            el('span', { className: 'acc-group__total' }, [
                `${g.items.length} ${g.items.length === 1 ? "attivita'" : "attivita'"}`,
                g.minutesLabel ? ` · ${g.minutesLabel}` : '',
            ]),
        ]),
        g.items.length
            ? el('div', { className: 'wk-steps' }, g.items.map((i) => taskRow(i, { showWho: data.scope === 'tutte' })))
            : el('p', { className: 'acc-empty' }, [icon('circle-check'), g.key === 'today' ? ' Niente di previsto per oggi.' : ' Niente.']),
    ]));

    // Gli appuntamenti dei prossimi giorni: non sono attivita', ma la
    // settimana non e' libera se ce n'e' uno (anche di domenica).
    const appointments = (data.appointments || []).length
        ? el('section', { className: 'card acc-section' }, [
              el('div', { className: 'acc-section__head' }, [
                  el('h2', { className: 'card__title' }, ['Appuntamenti in arrivo']),
                  el('a', { className: 'btn btn--ghost btn--small', href: '/appuntamenti' }, [icon('calendar'), 'Calendario']),
              ]),
              el('div', { className: 'acc-due-list' }, data.appointments.map((a) => el('a', { className: 'cal-row', href: a.href }, [
                  el('span', { className: `cal-row__dot cal-dot--${a.color}` }),
                  el('span', { className: 'cal-row__time' }, [a.dayLabel]),
                  el('span', { className: 'cal-row__body' }, [
                      el('span', { className: 'cal-row__title' }, [a.title]),
                      el('span', { className: 'cal-row__meta' }, [[a.time, a.kindLabel, a.location].filter(Boolean).join(' · ')]),
                  ]),
              ]))),
          ])
        : null;

    return el('div', { className: 'wk' }, [
        header,
        el('div', { className: 'stat-grid' }, (data.stats || []).map(renderComponent).filter(Boolean)),
        appointments,
        el('div', { className: 'wk-groups' }, groups),
    ].filter(Boolean));
}

registerComponent('task-board', renderTaskBoard);
