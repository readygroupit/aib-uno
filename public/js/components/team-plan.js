import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { actionButton } from '../accounts-ui.js';
import { dateJump } from '../date-jump.js';

// Il team in un colpo d'occhio: chi e' pieno, chi e' libero, cosa sta
// facendo adesso; le proposte per riequilibrare; la settimana a colori.

function personCard(p) {
    return el('a', { className: 'card pl-person', href: p.href }, [
        el('div', { className: 'pl-person__head' }, [
            el('span', { className: 'wk-who pl-person__avatar' }, [p.initials]),
            el('div', { className: 'pl-person__name' }, [
                el('strong', {}, [p.name]),
                el('span', { className: 'acc-muted' }, [p.working ? `${p.planned} su ${p.capacity}` : (p.absence ? p.absence.toLowerCase() : 'oggi a riposo')]),
            ]),
            p.working ? el('span', { className: `pl-person__load pl-person__load--${p.loadTone}` }, [`${p.load}%`]) : null,
        ]),
        p.working ? el('div', { className: 'pl-load__track' }, [el('span', { className: `pl-load__fill pl-load__fill--${p.loadTone === 'success' ? 'ok' : p.loadTone}`, style: `width:${Math.min(100, p.load)}%` })]) : null,
        el('div', { className: 'pl-person__now' }, [
            p.now ? el('span', {}, [el('strong', {}, ['Adesso: ']), `${p.now.title} (${p.now.until})`]) : null,
            p.next ? el('span', {}, [el('strong', {}, [`Alle ${p.next.time}: `]), p.next.title]) : null,
            !p.now && !p.next ? el('span', { className: 'acc-muted' }, ['Niente altro in programma oggi.']) : null,
        ]),
        el('div', { className: 'wk-task__chips' }, [
            p.appointments ? el('span', { className: 'wk-chip' }, [icon('calendar'), `${p.appointments} app.`]) : null,
            p.late ? el('span', { className: 'wk-chip wk-chip--danger' }, [`${p.late} in ritardo`]) : null,
            p.overflowLabel ? el('span', { className: 'wk-chip wk-chip--warning' }, [p.overflowLabel]) : null,
            p.freeLabel ? el('span', { className: 'wk-chip wk-chip--success' }, [p.freeLabel]) : null,
        ].filter(Boolean)),
    ]);
}

function renderTeam(data) {
    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, ['Il team oggi']),
        el('div', { className: 'claim-table__actions' }, [
            el('a', { className: 'btn btn--secondary', href: data.schedulesHref }, ['Orari di lavoro']),
        ]),
    ]);

    const suggestions = data.suggestions.length ? el('section', { className: 'card acc-section' }, [
        el('h2', { className: 'card__title' }, ['Per riequilibrare']),
        el('div', { className: 'acc-due-list' }, data.suggestions.map((s) => el('div', { className: 'acc-suggestion acc-suggestion--info pl-suggestion' }, [
            el('span', { className: 'acc-suggestion__icon' }, [icon('handshake')]),
            el('p', { className: 'acc-suggestion__text' }, [s.text]),
            actionButton(s.label, s.action, { variant: 'primary' }),
        ]))),
    ]) : null;

    const week = data.week;
    const table = el('table', { className: 'pl-week' }, [
        el('thead', {}, [el('tr', {}, [
            el('th', {}, ['']),
            ...week.days.map((d) => el('th', { className: d.isToday ? 'is-today' : '' }, [d.label])),
        ])]),
        el('tbody', {}, week.rows.map((r) => el('tr', {}, [
            el('th', {}, [el('span', { className: 'wk-who' }, [r.initials]), ` ${r.name}`]),
            ...r.cells.map((c) => el('td', {}, [c.past
                ? el('span', { className: 'pl-cell pl-cell--past' }, ['—'])
                : el('a', { className: `pl-cell pl-cell--${c.tone}`, href: c.href, title: c.detail }, [c.label])])),
        ]))),
    ]);

    return el('div', { className: 'pl' }, [
        header,
        el('div', { className: 'pl-people' }, data.people.map(personCard)),
        suggestions,
        el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [
                el('h2', { className: 'card__title' }, ['La settimana']),
                el('div', { className: 'cal-nav' }, [
                    el('a', { className: 'cal-nav__btn', href: data.prevWeek, 'aria-label': 'Settimana prima' }, [icon('chevron-left')]),
                    el('a', { className: 'cal-nav__btn', href: data.nextWeek, 'aria-label': 'Settimana dopo' }, [icon('chevron-right')]),
                    dateJump((date) => `/team?settimana=${date}`, { title: 'Vai alla settimana di un giorno' }),
                ]),
            ]),
            el('div', { className: 'pl-week__wrap' }, [table]),
            el('p', { className: 'acc-muted pl-week__legend' }, ['Percentuale della giornata pianificabile gia\' occupata da appuntamenti e lavoro. Oltre il 100% non ci sta tutto.']),
        ]),
    ].filter(Boolean));
}

registerComponent('team-plan', renderTeam);
