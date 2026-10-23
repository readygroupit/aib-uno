import { registerComponent, renderComponent } from './registry.js';
import { el } from '../dom.js';
import { icon } from '../icons.js';
import { badge, installmentRow, actionButton } from '../accounts-ui.js';

// Scadenzario: prima cio' che e' in ritardo, poi cio' che arriva. Ogni
// gruppo dice quanto vale; ogni riga ha "incassato" a portata di clic.

function groupBlock(group) {
    if (!group.items.length) return null;
    return el('section', { className: `card acc-section acc-group acc-group--${group.tone}` }, [
        el('div', { className: 'acc-section__head' }, [
            el('h2', { className: 'card__title' }, [group.title]),
            el('span', { className: 'acc-group__total' }, [
                `${group.items.length} ${group.items.length === 1 ? 'pagamento' : 'pagamenti'} · `,
                el('strong', {}, [group.totalLabel]),
            ]),
        ]),
        el('div', { className: 'acc-due-list' }, group.items.map((i) => installmentRow(i))),
    ]);
}

// Colonna stretta: importo e stato sotto il nome, azioni su una riga a se'.
function renewalRow(r) {
    return el('div', { className: `acc-due acc-due--renewal acc-due--${r.tone}` }, [
        el('div', { className: `acc-due__date acc-due__date--${r.tone}` }, [el('span', { className: 'acc-due__day' }, [r.date])]),
        el('div', { className: 'acc-due__text' }, [
            el('a', { className: 'acc-due__customer', href: r.customerHref }, [r.customer]),
            el('a', { className: 'acc-due__desc', href: r.href }, [r.title]),
            el('span', { className: 'acc-due__meta' }, [`${r.amount} · ${r.when}`]),
            el('span', { className: `acc-due__status acc-due__status--${r.tone}` }, [r.status]),
        ]),
        el('div', { className: 'acc-due__actions' }, [
            r.auto ? badge('automatico', 'info', 'arrows-rotate') : null,
            actionButton('Rinnova ora', r.renewAction, { confirm: `Rinnovare adesso "${r.title}" per un altro periodo?`, iconName: 'arrows-rotate' }),
        ]),
    ]);
}

function renderDueBoard(data) {
    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, [data.title]),
        el('div', { className: 'claim-table__actions' }, [
            data.createHref ? el('a', { className: 'btn btn--secondary', href: data.createHref }, [data.createLabel || 'Nuovo']) : null,
        ]),
    ]);

    const groups = data.groups.map(groupBlock).filter(Boolean);
    const main = groups.length
        ? groups
        : [el('section', { className: 'card acc-section' }, [el('p', { className: 'acc-empty' }, [icon('circle-check'), ' Nessun pagamento da incassare nei prossimi 90 giorni.'])])];

    const side = [];
    side.push(el('section', { className: 'card acc-section' }, [
        el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Rinnovi in arrivo'])]),
        data.renewals.length
            ? el('div', { className: 'acc-due-list' }, data.renewals.map(renewalRow))
            : el('p', { className: 'acc-empty' }, ['Nessun rinnovo nei prossimi 60 giorni.']),
    ]));
    if (data.recentPaid.length) {
        side.push(el('section', { className: 'card acc-section' }, [
            el('div', { className: 'acc-section__head' }, [el('h2', { className: 'card__title' }, ['Incassati di recente'])]),
            el('div', { className: 'acc-due-list' }, data.recentPaid.map((i) => installmentRow(i))),
        ]));
    }

    return el('div', { className: 'acc-board' }, [
        header,
        el('div', { className: 'stat-grid' }, data.stats.map(renderComponent).filter(Boolean)),
        el('div', { className: 'acc-board__layout' }, [
            el('div', { className: 'acc-board__main' }, main),
            el('div', { className: 'acc-board__side' }, side),
        ]),
    ]);
}

registerComponent('due-board', renderDueBoard);
