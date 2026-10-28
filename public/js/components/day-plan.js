import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { runAction, actionButton } from '../accounts-ui.js';
import { dateJump } from '../date-jump.js';

// La giornata messa in ordine: si legge dall'alto in basso come una
// scaletta. Si spunta quello che si fa, il piano si ricalcola da solo.

function checkButton(action, title) {
    const button = el('button', { type: 'button', className: 'wk-check', title, 'aria-label': title }, [icon('check')]);
    button.addEventListener('click', (e) => {
        e.preventDefault();
        runAction(action, { button });
    });
    return button;
}

function chips(row) {
    return el('div', { className: 'wk-task__chips' }, [
        row.duration ? el('span', { className: 'wk-chip', title: row.learned ? 'Tempo medio vero di questo lavoro, dal registro' : null }, [icon('clock'), row.duration, row.learned ? ' · dal registro' : '']) : null,
        row.part ? el('span', { className: 'wk-chip' }, [row.part]) : null,
        row.late ? el('span', { className: 'wk-chip wk-chip--danger' }, [row.late]) : null,
        row.deadline ? el('span', { className: 'wk-chip wk-chip--warning' }, [`scade alle ${row.deadline}`]) : null,
        row.priority === 'alta' && row.kind === 'task' ? el('span', { className: 'wk-chip wk-chip--danger' }, ['priorita alta']) : null,
    ].filter(Boolean));
}

/** Una riga della scaletta (usata anche dalla settimana di una persona, week-plan.js). */
export function timelineRow(row) {
    const time = el('span', { className: 'pl-row__time' }, [row.time.split('-')[0], el('small', {}, [row.time.split('-')[1]])]);

    if (row.type === 'appointment') {
        return el('div', { className: 'pl-row pl-row--appointment' }, [
            time,
            el('a', { className: 'pl-appointment', href: row.href }, [
                el('span', { className: 'pl-appointment__title' }, [icon(row.online ? 'video' : 'calendar'), row.title]),
                row.context ? el('span', { className: 'pl-appointment__meta' }, [row.context]) : null,
                row.checklist ? el('span', { className: 'pl-appointment__meta' }, [icon('check'), ` ${row.checklist} cose da portare`]) : null,
            ]),
            // Le fasi della commessa che si fanno durante l'appuntamento.
            ...(row.steps || []).map((step) => el('div', { className: 'wk-task pl-row__step' }, [
                checkButton(step.action, 'Segna come fatta'),
                el('div', { className: 'wk-task__body' }, [
                    el('a', { className: 'wk-task__title', href: step.href }, [step.title]),
                    el('span', { className: 'wk-task__context' }, ['Fase della commessa, si fa durante l\'appuntamento']),
                ]),
            ])),
            row.meetUrl ? el('a', { className: 'btn btn--secondary btn--small', href: row.meetUrl, target: '_blank', rel: 'noopener' }, [icon('video'), 'Meet']) : null,
        ]);
    }

    if (row.type === 'task' || row.type === 'reminder') {
        const lead = row.type === 'task'
            ? checkButton(row.action, 'Segna come fatta')
            : el('span', { className: 'pl-bell' }, [icon('bell')]);
        return el('div', { className: `pl-row pl-row--${row.type}` }, [
            time,
            el('div', { className: 'wk-task' }, [
                lead,
                el('div', { className: 'wk-task__body' }, [
                    el('a', { className: 'wk-task__title', href: row.href }, [row.title]),
                    row.context ? (row.contextHref ? el('a', { className: 'wk-task__context', href: row.contextHref }, [row.context]) : el('span', { className: 'wk-task__context' }, [row.context])) : null,
                    chips(row),
                ]),
                row.type === 'reminder' && row.action ? actionButton(row.actionLabel || 'Fatto', row.action, { iconName: 'check' }) : null,
                row.type === 'reminder' && !row.action && row.actionHref ? el('a', { className: 'btn btn--secondary btn--small', href: row.actionHref }, [icon(row.actionIcon || 'arrow-right'), row.actionLabel || 'Apri']) : null,
            ]),
        ]);
    }

    const labels = { travel: [icon('car'), row.title], break: [icon('clock'), 'Pausa'], free: [`${row.title} · ${Math.floor(row.minutes / 60) ? Math.floor(row.minutes / 60) + ' h ' : ''}${row.minutes % 60 ? (row.minutes % 60) + ' min' : ''}`] };
    return el('div', { className: `pl-row pl-row--${row.type}` }, [time, el('span', { className: 'pl-row__soft' }, labels[row.type] || [row.title])]);
}

function sideList(title, items, render, extra = null, tone = '') {
    if (!items.length) return null;
    return el('section', { className: `card acc-section pl-side ${tone}` }, [
        el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, [title]), extra]),
        el('div', { className: 'wk-steps' }, items.map(render)),
    ]);
}

function renderDayPlan(data) {
    const plan = data.plan;

    const nav = el('div', { className: 'cal-nav' }, [
        el('a', { className: 'cal-nav__btn', href: data.nav.prev, title: 'Giorno prima', 'aria-label': 'Giorno prima' }, [icon('chevron-left')]),
        el('a', { className: 'cal-nav__today' + (plan.isToday ? ' is-current' : ''), href: data.nav.today }, ['Oggi']),
        el('a', { className: 'cal-nav__btn', href: data.nav.next, title: 'Prossimo giorno di lavoro', 'aria-label': 'Prossimo giorno di lavoro' }, [icon('chevron-right')]),
        dateJump((date) => `/oggi?${data.isMe ? '' : `persona=${data.personId}&`}giorno=${date}`, { title: 'Vai a un giorno', value: plan.date }),
        el('a', { className: 'cal-nav__today', href: `/oggi?${data.isMe ? '' : `persona=${data.personId}&`}settimana=${plan.date}`, title: 'La settimana intera' }, ['Settimana']),
    ]);

    let personPicker = null;
    if (data.canTeam && data.people.length > 1) {
        personPicker = el('div', { className: 'wk-switch' }, data.people.map((p) => el('a', {
            className: 'wk-switch__item' + (p.id === data.personId ? ' is-active' : ''),
            href: `/oggi?persona=${p.id}` + (plan.isToday ? '' : `&giorno=${plan.date}`),
        }, [p.name])));
    }

    const loadTone = plan.loadPercent > 105 ? 'danger' : plan.loadPercent >= 80 ? 'warning' : 'ok';
    const hero = el('section', { className: 'card pl-hero' }, [
        el('div', { className: 'pl-hero__text' }, [
            el('span', { className: 'pl-hero__date' }, [data.dateLabel + (data.isMe ? '' : ` · giornata di ${data.name}`)]),
            el('h1', { className: 'pl-hero__title' }, [data.greeting.title]),
            el('p', { className: 'pl-hero__lead' }, [data.greeting.text]),
        ]),
        el('div', { className: 'pl-hero__side' }, [
            el('div', { className: 'pl-hero__controls' }, [personPicker, nav].filter(Boolean)),
            plan.working ? el('div', { className: 'pl-load' }, [
                el('div', { className: 'pl-load__numbers' }, [
                    el('strong', {}, [`${plan.loadPercent}%`]),
                    el('span', {}, [`della giornata (${plan.hours.start}-${plan.hours.end})`]),
                ]),
                el('div', { className: 'pl-load__track' }, [el('span', { className: `pl-load__fill pl-load__fill--${loadTone}`, style: `width:${Math.min(100, plan.loadPercent)}%` })]),
            ]) : null,
        ]),
    ]);

    const alerts = data.alerts.length ? el('section', { className: 'card pl-alerts' }, [
        el('h2', { className: 'card__title' }, ['Da sapere']),
        el('div', { className: 'pl-alerts__list' }, data.alerts.map((a) => el('a', { className: `pl-alert pl-alert--${a.tone}`, href: a.href }, [icon(a.icon), el('span', {}, [a.text])]))),
    ]) : null;

    // Scaletta, con la riga "Adesso" nel punto giusto (solo oggi).
    const now = new Date();
    const nowMin = now.getHours() * 60 + now.getMinutes();
    const rows = [];
    let nowShown = !plan.isToday;
    plan.timeline.forEach((row) => {
        if (!nowShown && row.end > nowMin) {
            rows.push(el('div', { className: 'pl-now' }, [el('span', {}, [`Adesso ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`])]));
            nowShown = true;
        }
        rows.push(timelineRow(row));
    });

    const schedule = el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [plan.isToday ? 'La scaletta di oggi' : 'La scaletta']),
            el('span', { className: 'acc-group__total' }, [`${plan.hours.start}-${plan.hours.end}`]),
        ]),
        rows.length
            ? el('div', { className: 'pl-timeline' }, rows)
            : el('p', { className: 'acc-empty' }, [plan.working
                ? (plan.isToday && plan.overflow.length ? 'La giornata di lavoro e\' finita: guarda qui accanto cosa e\' rimasto.' : 'Niente in programma.')
                : (plan.absence ? `${plan.absence}: niente lavoro in programma.` : 'Giorno di riposo.')]),
    ]);

    const overflowTotal = plan.overflowMinutes;
    const postponeAll = plan.overflow.some((i) => i.movable)
        ? actionButton(`Sposta a ${plan.nextWorkdayLabel}`, data.postponeAction, { variant: 'primary' })
        : null;

    const side = [
        sideList(data.evening ? 'Rimasto da fare' : 'Non ci sta oggi', plan.overflow, (i) => el('div', { className: 'wk-task' }, [
            el('div', { className: 'wk-task__body' }, [
                el('a', { className: 'wk-task__title', href: i.href }, [i.title]),
                i.context ? el('span', { className: 'wk-task__context' }, [i.context]) : null,
                el('div', { className: 'wk-task__chips' }, [
                    el('span', { className: 'wk-chip' }, [icon('clock'), i.duration]),
                    i.continues ? el('span', { className: 'wk-chip' }, ['prosegue']) : null,
                    i.late ? el('span', { className: 'wk-chip wk-chip--danger' }, [i.late]) : null,
                ].filter(Boolean)),
            ]),
        ]), postponeAll, 'pl-side--overflow'),
        sideList('Aspettano il cliente', plan.waiting, (w) => el('a', { className: 'acc-line', href: w.href }, [
            el('span', { className: 'acc-line__title' }, [icon('clock'), w.title]),
            w.context ? el('span', { className: 'acc-line__meta' }, [w.context]) : null,
        ])),
        sideList('Se avanzi tempo', data.upcoming, (u) => el('div', { className: 'wk-task' }, [
            checkButton(u.action, 'Segna come fatta'),
            el('div', { className: 'wk-task__body' }, [
                el('a', { className: 'wk-task__title', href: u.href }, [u.title]),
                el('span', { className: 'wk-task__context' }, [[u.context, u.duration, `previsto ${plan.nextWorkdayLabel}`].filter(Boolean).join(' · ')]),
            ]),
        ])),
        sideList('Fatto oggi', data.doneToday, (d) => el('div', { className: 'wk-task wk-task--done' }, [
            el('button', { type: 'button', className: 'wk-check is-done', title: 'Riapri', onClick: (e) => runAction(d.action, { button: e.currentTarget }) }, [icon('check')]),
            el('div', { className: 'wk-task__body' }, [el('a', { className: 'wk-task__title', href: d.href }, [d.title])]),
        ])),
    ].filter(Boolean);

    const evening = data.evening && overflowTotal > 0 ? el('div', { className: 'acc-suggestion acc-suggestion--warning' }, [
        el('span', { className: 'acc-suggestion__icon' }, [icon('clock')]),
        el('div', {}, [
            el('span', { className: 'acc-suggestion__title' }, ['Fine giornata']),
            el('p', { className: 'acc-suggestion__text' }, [`${data.doneToday.length ? (data.doneToday.length === 1 ? '1 cosa fatta oggi' : data.doneToday.length + ' cose fatte oggi') : 'Niente segnato come fatto oggi'}. Il resto lo sposto a ${plan.nextWorkdayLabel} con un clic, qui accanto.`]),
        ]),
        // Prima di chiudere: segnare cosa si e' fatto (registro attivita').
        window.UNO_WORKLOG && data.isMe ? el('a', { className: 'btn btn--primary btn--small pl-evening__btn', href: '/registro/check-in' }, ['Segna cosa hai fatto']) : null,
    ]) : null;

    return el('div', { className: 'pl' }, [
        hero,
        evening,
        alerts,
        el('div', { className: 'pl-layout' }, [
            el('div', { className: 'pl-layout__main' }, [schedule]),
            side.length ? el('div', { className: 'pl-layout__side' }, side) : null,
        ]),
    ].filter(Boolean));
}

registerComponent('day-plan', renderDayPlan);
