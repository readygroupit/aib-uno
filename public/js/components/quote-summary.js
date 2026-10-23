import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';

// Un preventivo: chi, quanto, a che punto, cosa manca; sotto le righe
// (cliccabili finche' e' modificabile) e le condizioni.
function renderQuoteSummary(data) {
    const facts = [
        data.customer ? el('a', { className: 'acc-fact', href: data.customerHref }, [icon('user'), data.customer]) : null,
        el('span', { className: 'acc-fact' }, [icon('calendar'), `${data.number} del ${data.date}`]),
        data.validUntil ? el('span', { className: 'acc-fact' }, [icon('clock'), `Valido fino al ${data.validUntil}`]) : null,
        data.owner ? el('span', { className: 'acc-fact' }, [icon('star'), `Lo segue ${data.owner}`]) : null,
    ].filter(Boolean);

    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [badge(data.stage.charAt(0).toUpperCase() + data.stage.slice(1), data.stageTone)]),
                    el('h1', { className: 'acc-profile__name' }, [data.title]),
                    el('div', { className: 'acc-profile__facts' }, facts),
                ]),
                actionsRow(data.actions),
            ]),
            data.approval || data.acceptance ? el('div', { className: 'ed-approvals' }, [
                data.approval ? el('span', { className: 'ed-approvals__item' }, [icon('circle-check'), data.approval]) : null,
                data.acceptance ? el('span', { className: 'ed-approvals__item' }, [icon('thumbs-up'), data.acceptance]) : null,
            ].filter(Boolean)) : null,
            data.problems.length ? el('div', { className: 'acc-suggestion acc-suggestion--warning' }, [
                el('span', { className: 'acc-suggestion__icon' }, [icon('circle-exclamation')]),
                el('div', {}, [
                    el('span', { className: 'acc-suggestion__title' }, ['Prima di mandarlo manca']),
                    el('p', { className: 'acc-suggestion__text' }, [data.problems.join(', ') + '.']),
                ]),
            ]) : null,
        ]),
        el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [
                el('h2', { className: 'card__title' }, ['Righe']),
                data.newLineHref ? el('a', { className: 'btn btn--ghost btn--small', href: data.newLineHref }, [icon('plus'), 'Riga']) : null,
            ]),
            data.lines.length
                ? el('div', { className: 'acc-due-list' }, data.lines.map((l) => el('div', { className: 'acc-due acc-due--nodate' }, [
                    el('div', { className: 'acc-due__text' }, [
                        l.href ? el('a', { className: 'acc-due__desc', href: l.href }, [l.description]) : el('span', { className: 'acc-due__desc' }, [l.description]),
                        el('span', { className: 'acc-due__meta' }, [[l.quantity, l.plan, l.details].filter(Boolean).join(' · ')]),
                    ]),
                    el('span', { className: 'acc-due__amount' }, [l.amount]),
                    l.serviceHref ? el('a', { className: 'btn btn--ghost btn--small', href: l.serviceHref }, [icon('link'), 'Servizio']) : null,
                ])))
                : el('p', { className: 'acc-empty' }, ['Nessuna riga. ', data.newLineHref ? el('a', { href: data.newLineHref }, ['Aggiungi la prima dal listino']) : null]),
            data.totals.length ? el('div', { className: 'inv-totals' }, [
                data.discount ? el('div', { className: 'inv-totals__line' }, [el('span', {}, ['Sconto']), el('span', {}, [data.discount])]) : null,
                ...data.totals.map((t) => el('div', { className: 'inv-totals__line inv-totals__line--total' }, [el('span', {}, [t.label]), el('span', {}, [t.value])])),
            ].filter(Boolean)) : null,
        ]),
        data.intro || data.conditions || data.paymentTerms ? el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Condizioni'])]),
            data.intro ? el('p', { className: 'qs-text' }, [data.intro]) : null,
            data.paymentTerms ? el('p', { className: 'qs-text' }, [el('strong', {}, ['Pagamento: ']), data.paymentTerms]) : null,
            data.revisions !== null && data.revisions !== undefined ? el('p', { className: 'qs-text' }, [el('strong', {}, ['Modifiche comprese: ']), `${data.revisions} giri per lavoro`]) : null,
            data.conditions ? el('p', { className: 'qs-text' }, [data.conditions]) : null,
        ].filter(Boolean)) : null,
    ].filter(Boolean));
}

registerComponent('quote-summary', renderQuoteSummary);
