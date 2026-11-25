import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { runAction } from '../accounts-ui.js';
import { guideParts } from '../guide.js';

// Il modello di una commessa, o il processo di un servizio: chi ci lavora
// (ruoli), le date chiave, le fasi in ordine (raggruppate per fase del
// processo), cosa si consegna. Ogni progetto nuovo di questo tipo copia
// tutto: le fasi diventano attivita' di chi ha il ruolo.
function stepItem(s) {
    return el('li', {}, [
        el('a', { className: 'wk-type-step', href: s.href }, [
            el('span', { className: 'wk-type-step__num' }, [String(s.position ?? '')]),
            el('span', { className: 'wk-type-step__title' }, [s.title]),
            el('span', { className: 'wk-task__chips' }, [
                s.role ? el('span', { className: 'wk-chip' }, [icon('user'), s.role]) : null,
                s.kind === 'approvazione' ? el('span', { className: 'wk-chip wk-chip--info' }, [icon('thumbs-up'), 'approvazione']) : null,
                s.kind === 'data chiave' ? el('span', { className: 'wk-chip wk-chip--info' }, [icon('calendar'), 'data chiave']) : null,
                s.auto ? el('span', { className: 'wk-chip wk-chip--success' }, [icon('arrows-rotate'), `da sola: ${s.auto}`]) : null,
                s.day ? el('span', { className: 'wk-chip' }, [icon('calendar'), s.day]) : null,
                s.estimate ? el('span', { className: 'wk-chip' }, [icon('clock'), s.estimate]) : null,
                s.checklist ? el('span', { className: 'wk-chip' }, [icon('check'), `${s.checklist} punti`]) : null,
                s.waits ? el('span', { className: 'wk-chip wk-chip--warning' }, [icon('clock'), 'aspetta il cliente']) : null,
                s.repeatable ? el('span', { className: 'wk-chip' }, [icon('arrows-rotate'), 'si ripete']) : null,
                s.optional ? el('span', { className: 'wk-chip' }, ['facoltativa']) : null,
            ].filter(Boolean)),
        ]),
    ]);
}

function stepsList(steps) {
    if (!steps.some((s) => s.phase)) {
        return el('ol', { className: 'wk-type-steps' }, steps.map(stepItem));
    }
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
    return el('div', { className: 'wk-phases' }, groups.map((g) => el('div', { className: 'wk-phase' }, [
        el('div', { className: 'wk-phase__head' }, [el('h3', { className: 'wk-phase__title' }, [g.name])]),
        el('ol', { className: 'wk-type-steps' }, g.items.map(stepItem)),
    ])));
}

// Aspetto delle quattro parti del processo: icona e colore aiutano a
// riconoscerle a colpo d'occhio (sempre le stesse, in tutto il gestionale).
const PARTS = {
    roles: { icon: 'users', color: 'lilac' },
    milestones: { icon: 'calendar-days', color: 'brass' },
    steps: { icon: 'list-check', color: 'teal' },
    deliverables: { icon: 'box-open', color: 'moss' },
};

function storedOpen(key, fallback) {
    try {
        const v = window.localStorage.getItem(`uno.process.${key}`);
        return v === null ? fallback : v === '1';
    } catch (e) {
        return fallback;
    }
}

function storeOpen(key, open) {
    try {
        window.localStorage.setItem(`uno.process.${key}`, open ? '1' : '0');
    } catch (e) {
        // senza memoria del browser si riapre come di default
    }
}

/**
 * Una parte del processo sulla linea del tempo: cerchietto con l'icona,
 * titolo con quanti sono e un riassunto, guida, "+ Aggiungi". Parte
 * chiusa; aperta mostra cosa e' (descrizione) e il suo contenuto. Il
 * browser si ricorda quali sono state aperte.
 */
function timelinePart({ key, title, count, summary, description, guide, addHref, addLabel, body }) {
    const look = PARTS[key];
    const open = storedOpen(key, false);
    const g = guideParts(guide, title);
    const bodyEl = el('div', { className: 'tl__body', id: `tl-body-${key}` }, [
        description ? el('p', { className: 'tl__desc' }, [description]) : null,
        body,
    ]);
    bodyEl.hidden = !open;

    const toggle = el('button', {
        type: 'button',
        className: 'tl__toggle',
        'aria-expanded': open ? 'true' : 'false',
        'aria-controls': `tl-body-${key}`,
    }, [
        el('span', { className: 'tl__title' }, [title]),
        el('span', { className: 'tl__count' }, [String(count)]),
        summary ? el('span', { className: 'tl__summary' }, [summary]) : null,
        el('span', { className: 'tl__chevron' }, [icon('chevron-down')]),
    ]);
    const item = el('li', { className: `tl__item tl__item--${look.color}` + (open ? ' is-open' : '') }, [
        el('span', { className: 'tl__marker', 'aria-hidden': 'true' }, [icon(look.icon)]),
        el('section', { className: 'tl__card' }, [
            el('div', { className: 'tl__head' }, [
                toggle,
                g.button,
                addHref ? el('a', { className: 'btn btn--ghost btn--small tl__add', href: addHref }, [icon('plus'), addLabel]) : null,
            ]),
            bodyEl,
        ]),
    ]);
    toggle.addEventListener('click', () => {
        const now = bodyEl.hidden;
        bodyEl.hidden = !now;
        toggle.setAttribute('aria-expanded', now ? 'true' : 'false');
        item.classList.toggle('is-open', now);
        storeOpen(key, now);
    });
    return item;
}

function simpleList(items, empty, addHref, render) {
    return items.length
        ? el('ul', { className: 'wk-type-steps' }, items.map((i) => el('li', {}, [render(i)])))
        : el('p', { className: 'acc-empty' }, [empty, ' ', addHref ? el('a', { href: addHref }, ['Aggiungi']) : null]);
}

function processHead(data, roles) {
    return el('section', { className: 'card acc-profile__head' }, [
        el('div', { className: 'acc-profile__identity' }, [
            el('div', { className: 'acc-profile__titles' }, [
                el('span', { className: 'kn-hint' }, ['Processo di lavoro']),
                el('h1', { className: 'acc-profile__name' }, [data.name]),
                el('div', { className: 'acc-profile__facts' }, [
                    roles.length ? el('span', { className: 'acc-fact' }, [icon('users'), `${roles.length} ruoli`]) : null,
                    el('span', { className: 'acc-fact' }, [icon('list-check'), `${data.steps.length} fasi`]),
                    data.days ? el('span', { className: 'acc-fact' }, [icon('calendar'), `circa ${data.days} giorni`]) : null,
                    data.total ? el('span', { className: 'acc-fact' }, [icon('clock'), `${data.total} di lavoro`]) : null,
                ].filter(Boolean)),
            ]),
            el('div', { className: 'acc-profile__actions' }, [
                el('a', { className: 'btn btn--primary', href: data.newOrderHref }, [roles.length ? 'Nuovo progetto con questo processo' : 'Nuova commessa di questo tipo']),
            ]),
        ]),
        data.description ? el('p', { className: 'wk-notes' }, [data.description]) : null,
    ]);
}

function renderTypeSteps(data) {
    const roles = data.roles || [];
    const milestones = data.milestones || [];
    const deliverables = data.deliverables || [];
    const guides = data.guides || {};
    const intros = data.intros || {};

    const parts = [];
    if (data.addRoleHref) {
        parts.push(timelinePart({
            key: 'roles',
            title: 'Chi ci lavora',
            count: roles.length,
            summary: roles.length ? roles.map((r) => r.label).join(', ') : 'nessun ruolo',
            description: intros.roles,
            guide: guides.roles,
            addHref: data.addRoleHref,
            addLabel: 'Ruolo',
            body: simpleList(roles, 'Nessun ruolo: le fasi vanno tutte al responsabile.', data.addRoleHref, (r) => el('a', { className: 'wk-type-step', href: r.href }, [
                el('span', { className: 'wk-type-step__title' }, [r.label]),
                el('span', { className: 'wk-task__chips' }, [
                    r.who ? el('span', { className: 'wk-chip' }, [icon('user'), `di solito ${r.who}`]) : null,
                    r.fromTeam ? el('span', { className: 'wk-chip' }, [`dalla squadra del cliente: ${r.fromTeam}`]) : null,
                    r.optional ? el('span', { className: 'wk-chip' }, ['facoltativo']) : null,
                ].filter(Boolean)),
            ])),
        }));
        parts.push(timelinePart({
            key: 'milestones',
            title: 'Date chiave',
            count: milestones.length,
            summary: milestones.length ? milestones.map((m) => m.label).join(', ') : 'nessuna data',
            description: intros.milestones,
            guide: guides.milestones,
            addHref: data.addMilestoneHref,
            addLabel: 'Data',
            body: simpleList(milestones, 'Nessuna data chiave.', data.addMilestoneHref, (m) => el('a', { className: 'wk-type-step', href: m.href }, [
                el('span', { className: 'wk-type-step__title' }, [m.label]),
                el('span', { className: 'wk-task__chips' }, [
                    m.by ? el('span', { className: 'wk-chip' }, [icon('user'), `la fissa ${m.by}`]) : null,
                    m.email ? el('span', { className: 'wk-chip wk-chip--info' }, [icon('envelope'), 'email al cliente']) : null,
                ].filter(Boolean)),
            ])),
        }));
    }

    parts.push(timelinePart({
        key: 'steps',
        title: 'Fasi',
        count: data.steps.length,
        summary: data.steps.length ? [...new Set(data.steps.map((s) => s.phase).filter(Boolean))].join(' · ') : 'ancora nessuna',
        description: intros.steps,
        guide: guides.steps,
        addHref: data.addHref,
        addLabel: 'Fase',
        body: data.steps.length
            ? stepsList(data.steps)
            : el('p', { className: 'acc-empty' }, ['Nessuna fase ancora. ', el('a', { href: data.addHref }, ['Aggiungi la prima'])]),
    }));

    if (data.addDeliverableHref) {
        parts.push(timelinePart({
            key: 'deliverables',
            title: 'Cosa si consegna',
            count: deliverables.length,
            summary: deliverables.length ? deliverables.map((d) => d.label).join(', ') : 'nessuna voce',
            description: intros.deliverables,
            guide: guides.deliverables,
            addHref: data.addDeliverableHref,
            addLabel: 'Voce',
            body: simpleList(deliverables, 'Nessuna voce.', data.addDeliverableHref, (d) => el('a', { className: 'wk-type-step', href: d.href }, [
                el('span', { className: 'wk-type-step__title' }, [d.label]),
                el('span', { className: 'wk-task__chips' }, [el('span', { className: 'wk-chip' + (d.always ? ' wk-chip--success' : '') }, [d.always ? 'sempre' : 'se venduto'])]),
            ])),
        }));
    }

    const timeline = el('ol', { className: 'tl' }, parts);
    if (data.embedded) {
        return el('div', { className: 'acc-profile' }, [timeline]);
    }
    return el('div', { className: 'acc-profile' }, [processHead(data, roles), timeline]);
}

/**
 * In testa alla pagina di un servizio (sopra le schede): nome, cosa e',
 * i numeri principali e il pulsante per partire con un progetto.
 */
function renderServiceHeader(data) {
    const g = guideParts(data.guide, data.name);
    return el('section', { className: 'card acc-profile__head svc-head' }, [
        el('div', { className: 'acc-profile__identity' }, [
            el('div', { className: 'acc-profile__titles' }, [
                el('span', { className: 'kn-hint' }, [data.kicker || 'Servizio']),
                el('h1', { className: 'acc-profile__name' }, [data.name, g.button]),
                el('div', { className: 'acc-profile__facts' }, (data.facts || []).map((f) => el('span', { className: `acc-fact svc-fact svc-fact--${f.color || 'petrol'}` }, [icon(f.icon || 'circle'), f.text]))),
            ]),
            data.action
                ? el('div', { className: 'acc-profile__actions' }, [el('a', { className: 'btn btn--primary', href: data.action.href }, [data.action.label])])
                : null,
        ]),
        data.description ? el('p', { className: 'wk-notes' }, [data.description]) : null,
    ]);
}

// Un servizio senza processo: si crea (con il suo nome) o si collega uno che c'e' gia'.
function renderServiceProcessMissing(data) {
    const create = el('button', { type: 'button', className: 'btn btn--primary' }, [icon('plus'), 'Crea il processo']);
    create.addEventListener('click', () => runAction(data.createAction, { button: create }));
    let link = null;
    if (data.existing.length) {
        const select = el('select', { className: 'field__input field__input--select', 'aria-label': 'Processo esistente' }, [
            el('option', { value: '' }, ['Collega un processo che c\u2019e\u2019 gia\u2019\u2026']),
            ...data.existing.map((t) => el('option', { value: String(t.id) }, [t.name])),
        ]);
        const go = el('button', { type: 'button', className: 'btn btn--secondary' }, ['Collega']);
        go.addEventListener('click', () => { if (select.value) runAction(data.createAction, { button: go, body: { type_id: select.value } }); });
        link = el('div', { className: 'dc-actions' }, [select, go]);
    }
    return el('section', { className: 'card acc-section' }, [
        el('h2', { className: 'card__title' }, ['Processo di lavoro']),
        el('p', { className: 'kn-hint' }, [`${data.service} non ha ancora un processo: fasi, chi le fa (ruoli), date chiave, cosa si consegna, cartelle sul Drive. Con il processo, ogni cliente che compra il servizio ha gia' il suo progetto pronto.`]),
        el('div', { className: 'dc-actions' }, [create, link]),
    ]);
}

registerComponent('type-steps', renderTypeSteps);
registerComponent('service-process-missing', renderServiceProcessMissing);
registerComponent('service-header', renderServiceHeader);
