import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, suggestionBox, avatar, installmentRow, actionButton } from '../accounts-ui.js';
import { progressBar } from '../work-ui.js';
import { healthDot, touchpointRow } from './client-health.js';

// Scheda cliente, in ordine di lettura: chi e' e cosa fare adesso, poi
// quanto vale (il dato protagonista nella card scura), poi servizi,
// incassi, referenti. I dati anagrafici restano sotto, in un form chiuso.

const RELATIONSHIP_VARIANT = { cliente: 'client', intermediario: 'partner', fornitore: 'supplier', collaboratore: 'supplier' };

function headerCard(c, data) {
    const facts = [
        c.since ? el('span', { className: 'acc-fact' }, [icon('calendar'), `Cliente dal ${c.since}`]) : null,
        c.subtitle ? el('span', { className: 'acc-fact' }, [icon('user'), c.subtitle]) : null,
        data.referrer ? el('span', { className: 'acc-fact' }, [icon('handshake'), 'Portato da ', el('a', { href: data.referrer.href }, [data.referrer.name])]) : null,
        c.phone ? el('a', { className: 'acc-fact', href: 'tel:' + c.phone }, [icon('phone'), c.phone]) : null,
        c.email ? el('a', { className: 'acc-fact', href: 'mailto:' + c.email, 'data-reload': '' }, [icon('envelope'), c.email]) : null,
    ].filter(Boolean);

    const tags = [
        c.isKey
            ? badge(`${c.relationship} chiave`, 'warning', 'star')
            : badge(c.relationship, c.relationshipKey === 'cliente' ? 'strong' : 'accent'),
        c.importance && !c.isKey ? badge(c.importance, 'neutral') : null,
    ].filter(Boolean);

    return el('section', { className: 'card acc-profile__head' }, [
        el('div', { className: 'acc-profile__identity' }, [
            avatar(c.initials, RELATIONSHIP_VARIANT[c.relationshipKey] || 'client', 'xl'),
            el('div', { className: 'acc-profile__titles' }, [
                el('div', { className: 'acc-profile__tags' }, tags),
                el('h1', { className: 'acc-profile__name' }, [c.name]),
                el('div', { className: 'acc-profile__facts' }, facts),
            ]),
            el('div', { className: 'acc-profile__actions' }, (data.actions || []).map((a) => (a.action
                ? actionButton(a.label, a.action, { variant: a.variant || 'secondary', iconName: a.icon || null })
                : el('a', { className: `btn btn--${a.variant || 'secondary'}`, href: a.href }, [a.label])))),
        ]),
        c.relationshipKey === 'intermediario' && (c.referralPercent || c.referralTerms)
            ? el('p', { className: 'acc-note' }, [icon('handshake'), 'Accordo: ', [c.referralPercent ? `${Number(c.referralPercent).toLocaleString('it-IT')}%` : null, c.referralTerms].filter(Boolean).join(' · ')])
            : null,
        suggestionBox(c.detail.suggestion),
    ]);
}

function kpiRow(c) {
    const d = c.detail;
    const annual = d.kpis.find((k) => k.label === 'Valore annuo');
    const others = d.kpis.filter((k) => k.label !== 'Valore annuo');
    const renewal = c.nextRenewal;

    return el('div', { className: 'acc-profile__kpis' }, [
        el('section', { className: 'card stat-box stat-box--feature acc-profile__feature' }, [
            el('div', { className: 'stat-box__header' }, [
                el('span', { className: 'stat-box__label' }, ['Valore annuo']),
                el('span', { className: 'stat-box__badge' }, [`${c.activeServicesCount} ${c.activeServicesCount === 1 ? 'servizio attivo' : 'servizi attivi'}`]),
            ]),
            el('div', { className: 'stat-box__value-row' }, [el('span', { className: 'stat-box__value' }, [annual ? annual.value : '—'])]),
            el('div', { className: 'stat-box__comparison' }, [c.annualCaption || 'servizi ricorrenti']),
        ]),
        ...others.map((k) => el('section', { className: `card acc-mini acc-mini--${k.tone}` }, [
            el('span', { className: 'acc-mini__label' }, [k.label]),
            el('span', { className: 'acc-mini__value' }, [k.value]),
            el('span', { className: 'acc-mini__sub' }, [k.tone === 'danger' ? c.payment.caption || '' : c.payment.tone === 'success' ? 'nessun ritardo' : c.payment.caption || '']),
        ])),
        el('section', { className: `card acc-mini acc-mini--${renewal ? renewal.tone : 'neutral'}` }, [
            el('span', { className: 'acc-mini__label' }, ['Prossimo rinnovo']),
            el('span', { className: 'acc-mini__value' }, [renewal ? renewal.date : '—']),
            el('span', { className: 'acc-mini__sub' }, [renewal ? `${renewal.title} · ${renewal.monthly ? 'mensile' : renewal.when}` : 'nessun servizio ricorrente']),
        ]),
    ]);
}

function serviceCard(s) {
    return el('article', { className: 'acc-service' + (s.stage !== 'attivo' ? ' acc-service--inactive' : '') }, [
        el('div', { className: 'acc-service__top' }, [
            el('a', { className: 'acc-service__title', href: s.href }, [s.title]),
            badge(s.stage.charAt(0).toUpperCase() + s.stage.slice(1), s.stageTone),
        ]),
        el('div', { className: 'acc-service__price' }, [s.price]),
        el('div', { className: 'acc-service__meta' }, [[s.kind, s.since].filter(Boolean).join(' · ')]),
        el('div', { className: 'acc-service__meta' }, [s.plan]),
        s.renewal ? el('div', { className: `acc-service__renewal acc-service__renewal--${s.renewal.tone}` }, [
            icon(s.renewal.auto ? 'arrows-rotate' : 'calendar'),
            el('span', {}, [`${s.renewal.auto ? 'Si rinnova da solo il' : 'Rinnovo il'} ${s.renewal.date}`]),
            el('span', { className: 'acc-service__when' }, [s.renewal.when]),
        ]) : null,
        el('div', { className: 'acc-service__foot' }, [
            s.margin ? el('span', { className: 'acc-muted' }, [s.margin]) : el('span'),
            s.canRenew && s.renewal && s.renewal.tone !== 'neutral'
                ? actionButton('Rinnova ora', `/servizi-clienti/${s.id}/rinnova`, { confirm: 'Rinnovare adesso per un altro periodo?', iconName: 'arrows-rotate' })
                : null,
        ]),
    ]);
}

function section(title, children, extra = null) {
    return el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, [title]), extra]),
        ...children,
    ]);
}

function renderCustomerProfile(data) {
    const c = data.customer;
    const blocks = [headerCard(c, data)];

    if (data.showBilling) {
        blocks.push(kpiRow(c));
    }

    // Temperatura: com'e' messo il rapporto e gli ultimi contatti (se il pacchetto c'e').
    if (data.health) {
        const h = data.health.health;
        blocks.push(section('Temperatura', [
            h ? el('div', { className: 'ch-profile' }, [
                el('div', { className: 'ch-profile__head' }, [
                    healthDot(h.tone, h.label),
                    el('span', { className: 'ch-profile__score' }, [`${h.score}°`]),
                    badge(h.label, h.tone),
                    h.lastContact ? el('span', { className: 'acc-muted' }, [`ultimo contatto ${h.lastContact}`]) : null,
                ]),
                h.reasons.length ? el('p', { className: 'ch-profile__reasons' }, [h.reasons.join(' · ')]) : null,
                h.level !== 'bene' ? el('span', { className: 'ch-row__suggestion' }, [icon('sparkle'), h.suggestion]) : null,
            ]) : el('p', { className: 'acc-empty' }, ['Si misura per i clienti con servizi attivi.']),
            data.health.touchpoints.length
                ? el('div', { className: 'ch-touches' }, data.health.touchpoints.map(touchpointRow))
                : el('p', { className: 'acc-empty' }, ['Nessun contatto registrato.']),
        ], el('a', { className: 'btn btn--ghost btn--small', href: data.health.logHref }, [icon('phone'), "Com'e' andata"])));
    }

    // Commesse (se il pacchetto c'e'): prima quelle che aspettano o sono in corso.
    if (data.workOrders) {
        blocks.push(section('Commesse', data.workOrders.length
            ? [el('div', { className: 'acc-due-list' }, data.workOrders.map((w) => el('a', { className: 'acc-line wk-order-line', href: w.href }, [
                el('span', { className: 'acc-line__title' }, [w.title, badge(w.stageLabel, w.stageTone)]),
                progressBar(w.progress),
                el('span', { className: 'acc-line__meta' }, [
                    w.active && w.next ? `${w.next.waits ? 'Aspettiamo' : 'Ora'}: ${w.next.title}` : '',
                    w.due ? el('span', { className: `acc-line__tag acc-line__tag--${w.due.tone}` }, [w.active ? `consegna ${w.due.date}` : w.due.when]) : null,
                ]),
            ])))]
            : [el('p', { className: 'acc-empty' }, ['Nessuna commessa. ', el('a', { href: data.newWorkOrderHref }, ['Apri la prima'])])],
        el('a', { className: 'btn btn--ghost btn--small', href: data.newWorkOrderHref }, [icon('plus'), 'Commessa'])));
    }

    if (data.appointments) {
        blocks.push(section('Prossimi appuntamenti', data.appointments.length
            ? [el('div', { className: 'acc-due-list' }, data.appointments.map((a) => el('a', { className: 'cal-row', href: a.href }, [
                el('span', { className: `cal-row__dot cal-dot--${a.color}` }),
                el('span', { className: 'cal-row__time' }, [a.dayLabel]),
                el('span', { className: 'cal-row__body' }, [
                    el('span', { className: 'cal-row__title' }, [a.title]),
                    el('span', { className: 'cal-row__meta' }, [[a.time, a.kindLabel, a.location].filter(Boolean).join(' · ')]),
                ]),
            ])))]
            : [el('p', { className: 'acc-empty' }, ['Nessun appuntamento in programma.'])],
        el('a', { className: 'btn btn--ghost btn--small', href: data.newAppointmentHref }, [icon('plus'), 'Appuntamento'])));
    }

    if (data.showBilling) {
        const newServiceHref = `/servizi-clienti/nuovo?customer_id=${c.id}`;
        blocks.push(section('Servizi', data.services.length
            ? [el('div', { className: 'acc-services' }, data.services.map(serviceCard))]
            : [el('p', { className: 'acc-empty' }, ['Nessun servizio ancora. ', el('a', { href: newServiceHref }, ['Aggiungi il primo'])])],
        el('a', { className: 'btn btn--ghost btn--small', href: newServiceHref }, [icon('plus'), 'Nuovo servizio'])));

        blocks.push(section('Pagamenti', data.installments.length
            ? [el('div', { className: 'acc-due-list' }, data.installments.map((i) => installmentRow(i, { showCustomer: false })))]
            : [el('p', { className: 'acc-empty' }, ['Nessun pagamento in programma.'])],
        el('a', { className: 'btn btn--ghost btn--small', href: `/scadenzario/nuovo?customer_id=${c.id}` }, [icon('plus'), 'Incasso a parte'])));
    }

    // La squadra interna: chi segue il cliente e per cosa (propone a chi assegnare contenuti e richieste).
    const teamHref = `/clienti/squadra/nuovo?customer_id=${c.id}`;
    blocks.push(section('Squadra interna', (data.team || []).length
        ? [el('div', { className: 'acc-chips' }, data.team.map((m) => el('a', { className: 'acc-chip', href: m.href, title: m.notes || '' }, [
            el('strong', {}, [m.name]), ` · ${m.role}`,
        ])))]
        : [el('p', { className: 'acc-empty' }, ['Chi segue questo cliente? ', el('a', { href: teamHref }, ['Indica la squadra']), ': contenuti e richieste si assegnano da soli.'])],
    el('a', { className: 'btn btn--ghost btn--small', href: teamHref }, [icon('plus'), 'Persona'])));

    const contactsHref = `/clienti/referenti/nuovo?customer_id=${c.id}`;
    blocks.push(section('Referenti', data.contacts.length
        ? [el('div', { className: 'acc-contacts' }, data.contacts.map((p) => el('a', { className: 'acc-contact', href: p.href }, [
            avatar(p.name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase(), 'person', 'sm'),
            el('span', { className: 'acc-contact__text' }, [
                el('span', { className: 'acc-contact__name' }, [p.name, p.decides ? badge('decide', 'warning') : null]),
                el('span', { className: 'acc-contact__meta' }, [[p.role, p.phone, p.email].filter(Boolean).join(' · ')]),
            ]),
        ])))]
        : [el('p', { className: 'acc-empty' }, ['Nessun referente. Chi decide per questo cliente? ', el('a', { href: contactsHref }, ['Aggiungilo'])])],
    el('a', { className: 'btn btn--ghost btn--small', href: contactsHref }, [icon('plus'), 'Referente'])));

    if (data.brought.length) {
        blocks.push(section(`Clienti portati (${data.brought.length})`, [
            el('div', { className: 'acc-chips' }, data.brought.map((b) => el('a', { className: 'acc-chip', href: b.href }, [b.name]))),
        ]));
    }

    return el('div', { className: 'acc-profile' }, blocks);
}

registerComponent('customer-profile', renderCustomerProfile);
