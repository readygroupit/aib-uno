import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, suggestionBox } from '../accounts-ui.js';
import { progressBar, taskRow, actionsRow } from '../work-ui.js';

// Scheda commessa: per chi e', a che punto e', la cosa da fare adesso,
// poi le fasi da spuntare nell'ordine. I dati restano sotto, chiusi.
function renderSummary(data) {
    const c = data.card;
    const facts = [
        el('a', { className: 'acc-fact', href: c.customerHref }, [icon('user'), c.customer]),
        c.owner ? el('span', { className: 'acc-fact' }, [icon('star'), `Responsabile ${c.owner}`]) : null,
        data.supplier ? el('a', { className: 'acc-fact', href: data.supplier.href }, [icon('handshake'), `Fornitore ${data.supplier.name}`]) : null,
        c.startDate ? el('span', { className: 'acc-fact' }, [icon('calendar'), `Inizio ${c.startDate}`]) : null,
        c.due ? el('span', { className: `acc-fact wk-fact--${c.due.tone}` }, [icon('calendar'), c.active ? `Consegna ${c.due.date} · ${c.due.when}` : c.due.when]) : null,
    ].filter(Boolean);

    const head = el('section', { className: 'card acc-profile__head' }, [
        el('div', { className: 'acc-profile__identity' }, [
            el('div', { className: 'acc-profile__titles' }, [
                el('div', { className: 'acc-profile__tags' }, [
                    badge(c.stageLabel, c.stageTone),
                    c.type ? badge(c.type, 'neutral') : null,
                    c.priority === 'alta' ? badge('Priorita alta', 'danger') : null,
                ].filter(Boolean)),
                el('h1', { className: 'acc-profile__name' }, [c.title]),
                el('div', { className: 'acc-profile__facts' }, facts),
            ]),
            actionsRow(data.actions),
        ]),
        progressBar(c.progress, { large: true }),
        c.flags.waiting && c.waitingReason ? el('p', { className: 'acc-note' }, [icon('clock'), `Aspettiamo dal cliente: ${c.waitingReason}`]) : null,
        suggestionBox(c.suggestion),
    ]);

    const h = data.hours;
    const stepsSection = el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, ['Fasi']),
            el('div', { className: 'wk-hours' }, [
                h.estimated ? el('span', {}, [`Previsto ${h.estimated}`]) : null,
                h.actual ? el('span', { className: h.over ? 'wk-hours--over' : '' }, [`impiegato ${h.actual}`, h.over ? ` (fasi fatte: ${h.doneEstimated} previste)` : '']) : null,
                data.expenses ? el('span', {}, [`spese ${data.expenses}`]) : null,
                data.newExpenseHref ? el('a', { className: 'btn btn--ghost btn--small', href: data.newExpenseHref }, [icon('coins'), 'Spesa']) : null,
                el('a', { className: 'btn btn--ghost btn--small', href: data.addStepHref }, [icon('plus'), 'Fase']),
            ]),
        ]),
        data.steps.length
            ? el('div', { className: 'wk-steps' }, data.steps.map((s) => taskRow(s, { showContext: false })))
            : el('p', { className: 'acc-empty' }, ['Nessuna fase. ', el('a', { href: data.addStepHref }, ['Aggiungi la prima'])]),
    ]);

    const blocks = [head, stepsSection];
    // Gli appuntamenti della commessa (es. la giornata di shooting), se c'e' il calendario.
    if (data.appointments) {
        blocks.push(el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [
                el('h2', { className: 'card__title' }, ['Appuntamenti']),
                el('a', { className: 'btn btn--ghost btn--small', href: data.addAppointmentHref }, [icon('plus'), 'Appuntamento']),
            ]),
            data.appointments.length
                ? el('div', { className: 'acc-due-list' }, data.appointments.map((a) => el('a', { className: 'cal-row' + (a.past || a.cancelled ? ' is-cancelled' : ''), href: a.href }, [
                    el('span', { className: `cal-row__dot cal-dot--${a.color}` }),
                    el('span', { className: 'cal-row__time' }, [a.dayLabel]),
                    el('span', { className: 'cal-row__body' }, [
                        el('span', { className: 'cal-row__title' }, [a.title]),
                        el('span', { className: 'cal-row__meta' }, [[a.time, a.kindLabel, a.location].filter(Boolean).join(' · ')]),
                    ]),
                ])))
                : el('p', { className: 'acc-empty' }, ['Nessun appuntamento. Uno shooting, un incontro? ', el('a', { href: data.addAppointmentHref }, ['Fissalo'])]),
        ]));
    }
    if (data.notes) {
        blocks.push(el('section', { className: 'card acc-section' }, [
            el('h2', { className: 'card__title' }, ['Note']),
            el('p', { className: 'wk-notes' }, [data.notes]),
        ]));
    }

    return el('div', { className: 'acc-profile' }, blocks);
}

registerComponent('work-order-summary', renderSummary);
