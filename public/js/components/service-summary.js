import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, installmentRow, actionButton } from '../accounts-ui.js';

// Testata di un servizio venduto e le sue scadenze; il form dei dati segue sotto, chiuso.
function renderServiceSummary(data) {
    const s = data.service;
    const facts = [
        data.customer ? el('a', { className: 'acc-fact', href: data.customer.href }, [icon('user'), data.customer.name]) : null,
        el('span', { className: 'acc-fact' }, [icon('coins'), s.price]),
        el('span', { className: 'acc-fact' }, [icon('calendar'), [s.kind, s.since].filter(Boolean).join(' · ')]),
        el('span', { className: 'acc-fact' }, [s.plan]),
        s.margin ? el('span', { className: 'acc-fact' }, [s.margin]) : null,
    ].filter(Boolean);

    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [badge(s.stage.charAt(0).toUpperCase() + s.stage.slice(1), s.stageTone)]),
                    el('h1', { className: 'acc-profile__name' }, [s.title]),
                    el('div', { className: 'acc-profile__facts' }, facts),
                ]),
                el('div', { className: 'acc-profile__actions' }, (data.actions || []).map((a) => actionButton(a.label, a.action, { confirm: a.confirm, variant: a.variant }))),
            ]),
            s.renewal ? el('div', { className: `acc-service__renewal acc-service__renewal--${s.renewal.tone}` }, [
                icon(s.renewal.auto ? 'arrows-rotate' : 'calendar'),
                el('span', {}, [`${s.renewal.auto ? 'Si rinnova da solo il' : 'Prossimo rinnovo il'} ${s.renewal.date}`]),
                el('span', { className: 'acc-service__when' }, [s.renewal.when]),
            ]) : null,
        ]),
        el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Pagamenti di questo servizio'])]),
            data.installments.length
                ? el('div', { className: 'acc-due-list' }, data.installments.map((i) => installmentRow(i, { showCustomer: false })))
                : el('p', { className: 'acc-empty' }, ['Nessun pagamento in programma.']),
        ]),
    ]);
}

registerComponent('service-summary', renderServiceSummary);
