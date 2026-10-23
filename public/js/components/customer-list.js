import { registerComponent, renderComponent } from './registry.js';
import { el, createSlidingIndicator } from '../dom.js';
import { icon } from '../icons.js';
import { badge, suggestionBox, avatar } from '../accounts-ui.js';

// Stesso impianto di claim-table (lista a sinistra, dettaglio a destra,
// schede e ricerca in alto): stesse classi di struttura, colonne proprie.

const RELATIONSHIP_VARIANT = { cliente: 'client', intermediario: 'partner', fornitore: 'supplier', collaboratore: 'supplier' };

function nameCell(row) {
    return el('span', { className: 'claim-table__cell-customer' }, [
        avatar(row.initials, RELATIONSHIP_VARIANT[row.relationshipKey] || 'client'),
        el('span', { className: 'claim-table__customer-text' }, [
            el('span', { className: 'claim-table__customer-name-row' }, [
                row.health ? el('span', { className: `ch-dot ch-dot--${row.health.tone} acc-list__health`, title: row.health.label }) : null,
                el('span', { className: 'claim-table__customer-name' }, [row.name]),
                row.isKey ? el('span', { className: 'acc-key', title: 'Cliente chiave' }, [icon('star')]) : null,
            ]),
            el('span', { className: 'claim-table__customer-sub' }, [
                row.relationshipKey !== 'cliente' ? `${row.relationship} · ` : '',
                row.subtitle || '',
            ]),
        ]),
    ]);
}

// Il primo servizio per nome, gli altri contati: righe tutte della stessa altezza.
function servicesCell(row) {
    if (!row.services.length) {
        return el('span', { className: 'acc-muted' }, [row.relationshipKey === 'cliente' ? 'Nessun servizio attivo' : '—']);
    }
    const others = row.activeServicesCount - 1;
    return el('span', { className: 'acc-when' }, [
        el('span', { className: 'acc-when__main acc-when__main--wrap' }, [
            row.services[0].recurring ? el('span', { className: 'acc-when__auto', title: 'Ricorrente' }, [icon('arrows-rotate')]) : null,
            el('span', { className: 'acc-ellipsis' }, [row.services[0].title]),
        ]),
        el('span', { className: 'acc-when__sub' }, [others > 0 ? `+ ${others} ${others === 1 ? 'altro servizio' : 'altri servizi'}` : '1 servizio']),
    ]);
}

function valueCell(row) {
    return el('span', { className: 'claim-table__amount' }, [
        el('span', { className: 'claim-table__amount-value' }, [row.annualValue || '—']),
        row.annualCaption ? el('span', { className: 'claim-table__amount-caption' }, [row.annualCaption]) : null,
    ]);
}

function statusCell(row) {
    const st = row.status;
    return el('span', { className: `acc-when acc-when--${st.tone}` }, [
        el('span', { className: 'acc-when__main' }, [st.icon ? icon(st.icon) : null, st.label]),
        st.caption ? el('span', { className: 'acc-when__sub' }, [st.caption]) : null,
    ]);
}

function buildRow(row, showBilling, onSelect) {
    const cells = showBilling
        ? [nameCell(row), servicesCell(row), valueCell(row), statusCell(row)]
        : [nameCell(row), el('span', { className: 'acc-muted' }, [row.relationship])];
    const node = el('button', {
        type: 'button',
        className: 'claim-table__row claim-table__row-grid ' + (showBilling ? 'acc-list__grid' : 'acc-list__grid--simple'),
        onClick: () => onSelect(row),
    }, cells);
    node.dataset.id = String(row.id);
    return node;
}

function detailPanel(row, showBilling, onClose) {
    const panel = el('aside', { className: 'card claim-detail acc-detail' });
    if (!row) {
        panel.appendChild(el('p', { className: 'claim-detail__empty' }, ["Seleziona un cliente dall'elenco."]));
        return panel;
    }
    const d = row.detail;

    panel.appendChild(el('div', { className: 'claim-detail__header' }, [
        avatar(row.initials, RELATIONSHIP_VARIANT[row.relationshipKey] || 'client', 'lg'),
        el('div', { className: 'claim-detail__header-text' }, [
            el('span', { className: 'claim-detail__name' }, [row.name]),
            el('span', { className: 'claim-detail__type' }, [
                [row.isKey ? `${row.relationship} chiave` : row.relationship, row.subtitle].filter(Boolean).join(' · '),
            ]),
        ]),
        el('button', { type: 'button', className: 'claim-detail__close', onClick: onClose, 'aria-label': 'Chiudi dettaglio' }, [icon('close')]),
    ]));

    const suggestion = suggestionBox(d.suggestion);
    if (suggestion) panel.appendChild(suggestion);

    if (showBilling) {
        panel.appendChild(el('div', { className: 'acc-kpis' }, d.kpis.map((k) => el('div', { className: `acc-kpi acc-kpi--${k.tone}` }, [
            el('span', { className: 'acc-kpi__label' }, [k.label]),
            el('span', { className: 'acc-kpi__value' }, [k.value]),
        ]))));

        if (d.services.length) {
            panel.appendChild(el('h4', { className: 'claim-detail__section-title' }, ['Servizi attivi']));
            panel.appendChild(el('div', { className: 'acc-lines' }, d.services.map((s) => el('a', { className: 'acc-line', href: s.href }, [
                el('span', { className: 'acc-line__title' }, [s.title]),
                el('span', { className: 'acc-line__meta' }, [
                    s.meta,
                    s.renewal ? el('span', { className: `acc-line__tag acc-line__tag--${s.renewal.tone}` }, [s.renewal.auto ? icon('arrows-rotate') : null, s.renewal.label]) : null,
                ]),
            ]))));
        }

        if (d.installments.length) {
            panel.appendChild(el('h4', { className: 'claim-detail__section-title' }, ['Prossimi incassi']));
            panel.appendChild(el('div', { className: 'acc-lines' }, d.installments.map((i) => el('div', { className: 'acc-line acc-line--static' }, [
                el('span', { className: 'acc-line__title' }, [i.label]),
                el('span', { className: 'acc-line__meta' }, [
                    el('span', { className: `acc-line__tag acc-line__tag--${i.tone}` }, [i.date]),
                    el('strong', {}, [i.amount]),
                ]),
            ]))));
        }
    }

    if (d.contacts.length) {
        panel.appendChild(el('h4', { className: 'claim-detail__section-title' }, ['Referenti']));
        panel.appendChild(el('div', { className: 'acc-lines' }, d.contacts.map((c) => el('div', { className: 'acc-line acc-line--static' }, [
            el('span', { className: 'acc-line__title' }, [c.name, c.decides ? badge('decide', 'warning') : null]),
            el('span', { className: 'acc-line__meta' }, [[c.role, c.phone, c.email].filter(Boolean).join(' · ')]),
        ]))));
    } else if (d.email || d.phone) {
        panel.appendChild(el('h4', { className: 'claim-detail__section-title' }, ['Contatti']));
        panel.appendChild(el('p', { className: 'acc-line__meta' }, [[d.phone, d.email].filter(Boolean).join(' · ')]));
    }

    if (d.referralCount > 0) {
        panel.appendChild(el('p', { className: 'acc-note' }, [icon('handshake'), `Ha portato ${d.referralCount} ${d.referralCount === 1 ? 'cliente' : 'clienti'}.`]));
    }

    panel.appendChild(el('div', { className: 'claim-detail__actions' }, [
        d.newServiceHref ? el('a', { className: 'btn btn--secondary', href: d.newServiceHref }, ['Nuovo servizio']) : null,
        el('a', { className: 'btn btn--primary', href: row.href }, ['Apri scheda']),
    ]));

    return panel;
}

function renderCustomerList(data) {
    const rows = data.rows || [];
    const showBilling = data.showBilling !== false;
    let activeTab = (data.filterTabs.find((t) => t.active) || data.filterTabs[0] || {}).value || 'tutti';
    let query = '';
    let selectedId = null;
    let closed = false;

    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, [data.title || 'Clienti']),
        el('div', { className: 'claim-table__actions' }, [
            data.createHref ? el('a', { className: 'btn btn--primary', href: data.createHref }, [data.createLabel || 'Nuovo']) : null,
        ]),
    ]);

    const statsGrid = data.stats.length ? el('div', { className: 'stat-grid' }, data.stats.map(renderComponent).filter(Boolean)) : null;

    const body = el('div', { className: 'claim-table__body' });
    const detailSlot = el('div', { className: 'claim-table__detail-slot' });

    const visibleRows = () => rows.filter((r) => r.buckets.includes(activeTab) && (!query || r.searchText.includes(query)));

    function renderDetail() {
        detailSlot.textContent = '';
        if (closed) return;
        detailSlot.appendChild(detailPanel(rows.find((r) => r.id === selectedId) || null, showBilling, () => {
            closed = true;
            renderDetail();
        }));
    }

    function select(row) {
        selectedId = row.id;
        closed = false;
        body.querySelectorAll('.claim-table__row').forEach((n) => n.classList.toggle('is-selected', n.dataset.id === String(row.id)));
        renderDetail();
    }

    function renderRows() {
        body.textContent = '';
        const list = visibleRows();
        if (!list.length) {
            body.appendChild(el('div', { className: 'claim-table__empty' }, [rows.length ? 'Nessun cliente con questi criteri.' : data.emptyMessage]));
            selectedId = null;
            renderDetail();
            return;
        }
        if (!list.some((r) => r.id === selectedId)) selectedId = list[0].id;
        list.forEach((row) => {
            const node = buildRow(row, showBilling, select);
            if (row.id === selectedId) node.classList.add('is-selected');
            body.appendChild(node);
        });
        renderDetail();
    }

    const tabs = el('div', { className: 'claim-table__tabs' });
    const moveIndicator = createSlidingIndicator(tabs, 'claim-table__tabs-indicator');
    data.filterTabs.forEach((tab) => {
        tabs.appendChild(el('button', {
            type: 'button',
            className: 'claim-table__tab' + (tab.value === activeTab ? ' is-active' : ''),
            onClick: (e) => {
                activeTab = tab.value;
                tabs.querySelectorAll('.claim-table__tab').forEach((b) => b.classList.remove('is-active'));
                e.currentTarget.classList.add('is-active');
                moveIndicator(e.currentTarget);
                renderRows();
            },
        }, [`${tab.label} `, el('span', { className: 'claim-table__tab-count' }, [String(tab.count)])]));
    });
    requestAnimationFrame(() => moveIndicator(tabs.querySelector('.is-active')));

    const search = el('input', { type: 'text', placeholder: 'Cerca cliente, settore, P.IVA...' });
    search.addEventListener('input', () => {
        query = search.value.trim().toLowerCase();
        renderRows();
    });

    const head = showBilling
        ? el('div', { className: 'claim-table__row-grid claim-table__head acc-list__grid' }, [
            el('span', {}, ['Cliente']),
            el('span', {}, ['Servizi attivi']),
            el('span', { className: 'claim-table__amount-head' }, ['Valore annuo']),
            el('span', {}, ['Situazione']),
        ])
        : el('div', { className: 'claim-table__row-grid claim-table__head acc-list__grid--simple' }, [el('span', {}, ['Cliente']), el('span', {}, ['Rapporto'])]);

    renderRows();

    const panel = el('div', { className: 'claim-table__panel' }, [
        el('div', { className: 'claim-table__toolbar' }, [tabs, el('label', { className: 'claim-table__search' }, [icon('search'), search])]),
        head,
        body,
    ]);

    return el('div', { className: 'acc-list' }, [
        header,
        statsGrid,
        el('div', { className: 'claim-table__layout' }, [el('div', { className: 'claim-table__main' }, [panel]), detailSlot]),
    ]);
}

registerComponent('customer-list', renderCustomerList);
