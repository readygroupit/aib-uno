import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, suggestionBox, shortList, runAction } from '../accounts-ui.js';
import { progressBar, taskRow, actionsRow, initialsBadge } from '../work-ui.js';
import { personAvatar } from '../avatars.js';
import { guideParts } from '../guide.js';

// Una fase del processo: la riga da spuntare, chi la fa per ruolo e, se
// tocca a chi guarda, i pulsanti (approva, rimanda indietro, non la facciamo).
function processStep(s) {
    const row = taskRow(s, { showContext: false });
    const extra = [
        s.roleLabel ? el('span', { className: 'wk-chip' }, [icon('user'), s.roleLabel]) : null,
        s.kind === 'approvazione' ? el('span', { className: 'wk-chip wk-chip--info' }, [icon('thumbs-up'), 'approvazione']) : null,
        s.optional ? el('span', { className: 'wk-chip' }, [s.skipped ? 'saltata' : 'facoltativa']) : null,
    ].filter(Boolean);
    const body = row.querySelector('.wk-task__body');
    if (body && extra.length) {
        let chips = body.querySelector('.wk-task__chips');
        if (!chips) {
            chips = el('div', { className: 'wk-task__chips' });
            body.appendChild(chips);
        }
        extra.forEach((c) => chips.appendChild(c));
    }
    if (body && s.actions && s.actions.length) {
        body.appendChild(actionsRow(s.actions));
    }
    return row;
}

function processSteps(steps) {
    const groups = [];
    steps.forEach((s) => {
        const name = s.phase || 'Altre fasi';
        let group = groups.find((g) => g.name === name);
        if (!group) {
            group = { name, items: [] };
            groups.push(group);
        }
        group.items.push(s);
    });
    return el('div', { className: 'wk-phases' }, groups.map((g) => {
        const done = g.items.filter((i) => i.done).length;
        return el('div', { className: 'wk-phase' + (done === g.items.length ? ' is-done' : '') }, [
            el('div', { className: 'wk-phase__head' }, [
                el('h3', { className: 'wk-phase__title' }, [g.name]),
                done === g.items.length ? el('span', { className: 'wk-phase__done' }, [icon('check'), 'tutte fatte']) : null,
                el('span', { className: 'wk-phase__count' }, [`${done}/${g.items.length}`]),
            ]),
            el('div', { className: 'wk-steps' }, g.items.map(processStep)),
        ]);
    }));
}

// Titolo di un riquadro con la sua guida "i" (e un contatore, se c'e').
function sectionTitle(text, guide, count = null) {
    const g = guideParts(guide, text);
    return el('h2', { className: 'card__title wo-title' }, [text, count !== null ? el('span', { className: 'acc-count' }, [String(count)]) : null, g.button]);
}

function milestonesSection(milestones, guide) {
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [sectionTitle('Date chiave', guide)]),
        el('div', { className: 'wk-milestones' }, milestones.map((m) => el('a', { className: 'wk-milestone' + (m.set ? ' is-set' : '') + (m.past ? ' is-past' : ''), href: m.href }, [
            el('span', { className: 'wk-milestone__label' }, [m.label]),
            el('span', { className: 'wk-milestone__when' }, [icon('calendar'), m.when]),
            m.email ? el('span', { className: 'wk-milestone__email' }, [icon('envelope'), m.email]) : null,
            !m.set ? el('span', { className: 'btn btn--secondary btn--small' }, ['Fissa la data']) : null,
        ]))),
    ]);
}

function teamSection(team, guide) {
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            sectionTitle('Squadra', guide),
            el('a', { className: 'btn btn--ghost btn--small', href: team.addHref }, [icon('plus'), 'Persona']),
        ]),
        team.members.length || team.missing.length
            ? el('div', { className: 'wk-team' }, [
                ...team.members.map((m) => el('a', { className: 'wk-member', href: m.href }, [
                    m.avatar ? personAvatar({ avatar: m.avatar, initials: m.initials, name: m.name }, 32) : initialsBadge(m.initials, m.name),
                    el('span', { className: 'wk-member__text' }, [
                        el('span', { className: 'wk-member__name' }, [m.name]),
                        m.role ? el('span', { className: 'wk-member__role' }, [m.role]) : null,
                    ]),
                ])),
                ...team.missing.map((r) => el('a', { className: 'wk-member wk-member--missing', href: r.addHref }, [
                    el('span', { className: 'wk-who wk-who--empty' }, ['?']),
                    el('span', { className: 'wk-member__text' }, [
                        el('span', { className: 'wk-member__name' }, ['Chi?']),
                        el('span', { className: 'wk-member__role' }, [r.label]),
                    ]),
                ])),
            ])
            : el('p', { className: 'acc-empty' }, ['Nessuno ancora. ', el('a', { href: team.addHref }, ['Componi la squadra'])]),
    ]);
}

function deliverablesSection(d, guide) {
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            sectionTitle('Cosa si consegna', guide, d.items.length ? `${d.done}/${d.items.length}` : null),
            el('a', { className: 'btn btn--ghost btn--small', href: d.addHref }, [icon('plus'), 'Voce']),
        ]),
        d.items.length
            ? el('div', { className: 'wk-steps' }, d.items.map((item) => {
                const check = el('button', { type: 'button', className: 'wk-check' + (item.done ? ' is-done' : ''), title: item.done ? 'Da fare' : 'Pronto' }, [icon('check')]);
                check.addEventListener('click', (e) => { e.preventDefault(); runAction(item.toggleAction, { button: check }); });
                return el('div', { className: 'wk-task' + (item.done ? ' wk-task--done' : '') }, [
                    check,
                    el('div', { className: 'wk-task__body' }, [
                        el('a', { className: 'wk-task__title', href: item.href }, [item.label]),
                        item.description ? el('span', { className: 'wk-task__context' }, [item.description]) : null,
                    ]),
                ]);
            }))
            : el('p', { className: 'acc-empty' }, ['Niente ancora. ', el('a', { href: d.addHref }, ['Aggiungi'])]),
    ]);
}

function changesSection(c, guide) {
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            sectionTitle('Modifiche chieste dal cliente', guide, c.items.length ? c.items.length : null),
            el('a', { className: 'btn btn--ghost btn--small', href: c.addHref }, [icon('plus'), 'Modifica']),
        ]),
        c.items.length
            ? shortList(c.items.map((m) => el('a', { className: 'acc-due acc-due--nodate', href: m.href }, [
                el('div', { className: 'acc-due__text' }, [
                    el('span', { className: 'acc-due__desc' }, [m.description]),
                    el('span', { className: 'acc-due__meta' }, [[m.when, m.who].filter(Boolean).join(' · ')]),
                ]),
                el('div', { className: 'acc-due__actions' }, [
                    m.extra ? badge('extra', 'warning') : badge('compresa', 'neutral'),
                    badge(m.stage, m.stage === 'aperta' ? 'info' : 'success'),
                ]),
            ])))
            : el('p', { className: 'acc-empty' }, ['Nessuna modifica, per ora.']),
    ]);
}

// In testa al progetto: per chi e', a che punto e', le azioni del momento
// e la cosa da fare adesso. Il resto sta nelle schede (componenti 'wo-*').
function renderSummary(data) {
    const c = data.card;
    const g = guideParts(data.guide, c.title);
    const facts = [
        el('a', { className: 'acc-fact', href: c.customerHref }, [icon('user'), c.customer]),
        c.owner ? el('span', { className: 'acc-fact' }, [icon('star'), `Responsabile ${c.owner}`]) : null,
        data.supplier ? el('a', { className: 'acc-fact', href: data.supplier.href }, [icon('handshake'), `Fornitore ${data.supplier.name}`]) : null,
        c.startDate ? el('span', { className: 'acc-fact' }, [icon('calendar'), `Inizio ${c.startDate}`]) : null,
        c.due ? el('span', { className: `acc-fact wk-fact--${c.due.tone}` }, [icon('calendar'), c.active ? `Consegna ${c.due.date} · ${c.due.when}` : c.due.when]) : null,
    ].filter(Boolean);

    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [
                        badge(c.stageLabel, c.stageTone),
                        c.type ? badge(c.type, 'neutral') : null,
                        c.priority === 'alta' ? badge('Priorita alta', 'danger') : null,
                    ].filter(Boolean)),
                    el('h1', { className: 'acc-profile__name wo-title' }, [c.title, g.button]),
                    el('div', { className: 'acc-profile__facts' }, facts),
                ]),
                actionsRow(data.actions),
            ]),
            progressBar(c.progress, { large: true }),
            c.flags.waiting && c.waitingReason ? el('p', { className: 'acc-note' }, [icon('clock'), `Aspettiamo dal cliente: ${c.waitingReason}`]) : null,
            suggestionBox(c.suggestion),
        ]),
    ]);
}

function hideDonePref() {
    try {
        return window.localStorage.getItem('uno.steps.hideDone') !== '0';
    } catch (e) {
        return true;
    }
}

// Le fasi del progetto (da spuntare nell'ordine), con ore e spese. Con
// "Nascondi le fatte" (acceso di partenza) restano in vista solo quelle da
// fare: un gruppo tutto fatto diventa una riga sola.
function renderSteps(data) {
    const h = data.hours;
    const all = data.process ? data.process.steps : data.steps;
    const doneCount = all.filter((s) => s.done).length;
    let hide = hideDonePref();
    const sw = el('input', { type: 'checkbox', className: 'wo-switch__input', checked: hide ? 'checked' : null });
    const toggle = doneCount ? el('label', { className: 'wo-switch' }, [sw, el('span', { className: 'wo-switch__track', 'aria-hidden': 'true' }), `Nascondi le fatte (${doneCount})`]) : null;
    let section = null;
    sw.addEventListener('change', () => {
        hide = sw.checked;
        try { window.localStorage.setItem('uno.steps.hideDone', hide ? '1' : '0'); } catch (e) { /* resta per questa pagina */ }
        if (section) section.classList.toggle('wo-hide-done', hide);
    });
    section = el('section', { className: 'card acc-section' + (hide && doneCount ? ' wo-hide-done' : '') }, [
        el('div', { className: 'acc-section__head' }, [
            el('div', { className: 'wo-steps-title' }, [sectionTitle('Fasi', data.guide), toggle]),
            el('div', { className: 'wk-hours' }, [
                h.estimated && !/^0\s/.test(h.estimated) ? el('span', {}, [`Previsto ${h.estimated}`]) : null,
                h.actual ? el('span', { className: h.over ? 'wk-hours--over' : '' }, [`impiegato ${h.actual}`, h.over ? ` (fasi fatte: ${h.doneEstimated} previste)` : '']) : null,
                data.expenses ? el('span', {}, [`spese ${data.expenses}`]) : null,
                data.newExpenseHref ? el('a', { className: 'btn btn--ghost btn--small', href: data.newExpenseHref }, [icon('coins'), 'Spesa']) : null,
                el('a', { className: 'btn btn--ghost btn--small', href: data.addStepHref }, [icon('plus'), 'Fase']),
            ]),
        ]),
        data.process && data.process.hasPhases
            ? processSteps(data.process.steps)
            : data.steps.length
            ? el('div', { className: 'wk-steps' }, data.process ? data.process.steps.map(processStep) : data.steps.map((s) => taskRow(s, { showContext: false })))
            : el('p', { className: 'acc-empty' }, ['Nessuna fase. ', el('a', { href: data.addStepHref }, ['Aggiungi la prima'])]),
    ]);
    return section;
}

// Gli appuntamenti del progetto (es. la giornata di shooting).
function renderAppointments(data) {
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [
            sectionTitle('Appuntamenti', data.guide),
            el('a', { className: 'btn btn--ghost btn--small', href: data.addHref }, [icon('plus'), 'Appuntamento']),
        ]),
        data.items.length
            ? shortList(data.items.map((a) => el('a', { className: 'cal-row' + (a.past || a.cancelled ? ' is-cancelled' : ''), href: a.href }, [
                el('span', { className: `cal-row__dot cal-dot--${a.color}` }),
                el('span', { className: 'cal-row__time' }, [a.dayLabel]),
                el('span', { className: 'cal-row__body' }, [
                    el('span', { className: 'cal-row__title' }, [a.title]),
                    el('span', { className: 'cal-row__meta' }, [[a.time, a.kindLabel, a.location].filter(Boolean).join(' · ')]),
                ]),
            ])))
            : el('p', { className: 'acc-empty' }, ['Nessun appuntamento. Uno shooting, un incontro? ', el('a', { href: data.addHref }, ['Fissalo'])]),
    ]);
}

registerComponent('work-order-summary', renderSummary);
registerComponent('wo-steps', renderSteps);
registerComponent('wo-team', (d) => teamSection(d.team, d.guide));
registerComponent('wo-milestones', (d) => milestonesSection(d.milestones, d.guide));
registerComponent('wo-deliverables', (d) => deliverablesSection(d.deliverables, d.guide));
registerComponent('wo-changes', (d) => changesSection(d.changes, d.guide));
registerComponent('wo-appointments', renderAppointments);
