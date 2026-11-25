import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge } from '../accounts-ui.js';
import { actionsRow } from '../work-ui.js';
import { guideParts } from '../guide.js';

// In testa al preventivo: stato, titolo, cliente, date, chi lo segue, le
// azioni del momento e cosa manca prima di mandarlo. Le righe sono nella
// scheda "Righe" (componente 'quote-lines'), i dati nella scheda del form.
function renderQuoteSummary(data) {
    const q = data;
    const g = guideParts(data.guide, q.title);
    const facts = [
        q.customer ? el('a', { className: 'acc-fact', href: q.customerHref }, [icon('user'), q.customer]) : null,
        el('span', { className: 'acc-fact' }, [icon('calendar'), `${q.number} del ${q.date}`]),
        q.validUntil ? el('span', { className: 'acc-fact' }, [icon('clock'), `Valido fino al ${q.validUntil}`]) : null,
        q.owner ? el('span', { className: 'acc-fact' }, [icon('star'), `Lo segue ${q.owner}`]) : null,
        q.totals && q.totals.length ? el('span', { className: 'acc-fact qs-total' }, [icon('euro-sign'), q.totals.map((t) => t.value).join(' + ')]) : null,
    ].filter(Boolean);

    return el('div', { className: 'acc-profile' }, [
        el('section', { className: 'card acc-profile__head' }, [
            el('div', { className: 'acc-profile__identity' }, [
                el('div', { className: 'acc-profile__titles' }, [
                    el('div', { className: 'acc-profile__tags' }, [badge(q.stage.charAt(0).toUpperCase() + q.stage.slice(1), q.stageTone)]),
                    el('h1', { className: 'acc-profile__name qs-name' }, [q.title, g.button]),
                    q.project ? el('a', { className: 'acc-fact wk-context', href: q.project.href }, [icon('arrow-left'), q.project.label]) : null,
                    el('div', { className: 'acc-profile__facts' }, facts),
                ]),
                actionsRow(q.actions),
            ]),
            q.approval || q.acceptance ? el('div', { className: 'ed-approvals' }, [
                q.approval ? el('span', { className: 'ed-approvals__item' }, [icon('circle-check'), q.approval]) : null,
                q.acceptance ? el('span', { className: 'ed-approvals__item' }, [icon('thumbs-up'), q.acceptance]) : null,
            ].filter(Boolean)) : null,
            q.problems.length ? el('div', { className: 'acc-suggestion acc-suggestion--warning' }, [
                el('span', { className: 'acc-suggestion__icon' }, [icon('circle-exclamation')]),
                el('div', {}, [
                    el('span', { className: 'acc-suggestion__title' }, ['Prima di mandarlo manca']),
                    el('p', { className: 'acc-suggestion__text' }, [q.problems.join(', ') + '.']),
                    // Dove si sistema: i dati del cliente nel pannello, le schede qui sotto.
                    q.fixes && q.fixes.length ? el('div', { className: 'qs-fixes' }, q.fixes.map((f) => el('a', { className: 'btn btn--secondary btn--small', href: f.href }, [icon(f.icon || 'arrow-right'), f.label]))) : null,
                ]),
            ]) : null,
        ]),
    ]);
}

// Le righe del preventivo (cliccabili finche' e' modificabile) e i totali.
function renderQuoteLines(data) {
    const g = guideParts(data.guide, 'Righe');
    return el('section', { className: 'card acc-section qs-lines' }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title qs-name' }, ['Righe', g.button]),
            data.newLineHref ? el('a', { className: 'btn btn--ghost btn--small', href: data.newLineHref }, [icon('plus'), 'Riga']) : null,
        ]),
        data.editable ? null : el('p', { className: 'kn-hint' }, ['Il preventivo non e’ piu’ in bozza: le righe non si cambiano.']),
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
    ]);
}

registerComponent('quote-summary', renderQuoteSummary);
registerComponent('quote-lines', renderQuoteLines);
