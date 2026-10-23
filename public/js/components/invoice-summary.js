import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';

// Scheda fattura: chi, quanto, a che punto (emissione, SdI, pagamento) e
// il passo successivo. Se mancano dati per l'XML lo dice prima di emettere.
function renderInvoiceSummary(data) {
    const facts = [
        data.customer ? el('a', { className: 'acc-fact', href: data.customerHref }, [icon('user'), data.customer]) : null,
        data.date ? el('span', { className: 'acc-fact' }, [icon('calendar'), `Del ${data.date}`]) : null,
        data.due ? el('span', { className: 'acc-fact' }, [icon('clock'), `Scadenza ${data.due}`]) : null,
        data.method ? el('span', { className: 'acc-fact' }, [icon('coins'), data.method]) : null,
        data.installmentHref ? el('a', { className: 'acc-fact', href: data.installmentHref }, [icon('link'), 'Incasso collegato']) : null,
    ].filter(Boolean);

    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [
                        badge(data.stage.charAt(0).toUpperCase() + data.stage.slice(1), data.stageTone),
                        data.sdi ? badge(data.sdi, data.sdiTone) : null,
                    ].filter(Boolean)),
                    el('h1', { className: 'acc-profile__name' }, [data.title]),
                    el('div', { className: 'acc-profile__facts' }, facts),
                ]),
                actionsRow(data.actions),
            ]),
            data.problems.length
                ? el('div', { className: 'acc-suggestion acc-suggestion--warning' }, [
                    el('span', { className: 'acc-suggestion__icon' }, [icon('circle-exclamation')]),
                    el('div', {}, [
                        el('span', { className: 'acc-suggestion__title' }, ['Per la fattura elettronica manca']),
                        el('p', { className: 'acc-suggestion__text' }, [data.problems.join(', ') + '.']),
                    ]),
                ])
                : null,
        ]),
        el('section', { className: 'card acc-section inv-totals' }, [
            data.description ? el('p', { className: 'inv-totals__desc' }, [data.description]) : null,
            ...data.lines.map((l) => el('div', { className: 'inv-totals__line' + (l.strong ? ' inv-totals__line--total' : '') }, [
                el('span', {}, [l.label]),
                el('span', {}, [l.value]),
            ])),
        ]),
    ]);
}

registerComponent('invoice-summary', renderInvoiceSummary);
