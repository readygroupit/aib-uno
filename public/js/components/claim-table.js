import { registerComponent, renderComponent } from './registry.js';
import { el, createSlidingIndicator } from '../dom.js';

const VARIANT_LABELS = { accent: 'lilac', info: 'info', success: 'success', warning: 'warning', danger: 'danger', neutral: 'neutral' };

function badge(text, variant) {
    return el('span', { className: `badge badge--${VARIANT_LABELS[variant] || 'neutral'}` }, [text]);
}

function docsProgress(total, complete) {
    if (total === 0) {
        return el('span', { className: 'claim-table__docs-empty' }, ['—']);
    }

    const segments = [];
    for (let i = 0; i < total; i++) {
        segments.push(el('span', { className: 'claim-table__docs-seg' + (i < complete ? ' is-complete' : '') }));
    }

    return el('div', { className: 'claim-table__docs' }, [
        el('span', { className: 'claim-table__docs-label' }, [complete === total ? 'Completi' : `${complete} di ${total}`]),
        el('div', { className: 'claim-table__docs-bar' }, segments),
    ]);
}

function buildRow(row, onSelect) {
    const cells = [
        el('td', {}, [
            el('div', { className: 'claim-table__cell-customer' }, [
                el('span', { className: 'claim-table__avatar' }, [row.avatarInitials]),
                el('div', { className: 'claim-table__customer-text' }, [
                    el('div', { className: 'claim-table__customer-name-row' }, [
                        el('span', { className: 'claim-table__customer-name' }, [row.name]),
                        row.companyTag ? el('span', { className: 'badge badge--neutral' }, [row.companyTag]) : null,
                    ]),
                    el('span', { className: 'claim-table__customer-email' }, [row.email || '']),
                ]),
            ]),
        ]),
        el('td', {}, [
            el('div', { className: 'claim-table__flight' }, [
                el('span', {}, [row.flightLabel || '—']),
                row.disservizioLabel ? el('span', { className: 'claim-table__flight-sub' }, [row.disservizioLabel]) : null,
            ]),
        ]),
        el('td', {}, [row.stageLabel ? badge(row.stageLabel, row.stageVariant) : '—']),
        el('td', {}, [docsProgress(row.docsTotal, row.docsComplete)]),
        el('td', { className: 'claim-table__amount-cell' }, [
            el('div', { className: 'claim-table__amount' }, [
                el('span', { className: 'claim-table__amount-value' }, [row.amountValue || '—']),
                row.amountCaption ? el('span', { className: 'claim-table__amount-caption' }, [row.amountCaption]) : null,
            ]),
        ]),
    ];

    const tr = el('tr', { className: 'claim-table__row', onClick: () => onSelect(row) }, cells);

    return tr;
}

function timelineNode(steps) {
    return el('div', { className: 'claim-timeline' }, steps.map((step) =>
        el('div', { className: 'claim-timeline__step claim-timeline__step--' + step.state }, [
            el('span', { className: 'claim-timeline__dot' }),
            el('div', { className: 'claim-timeline__text' }, [
                el('span', { className: 'claim-timeline__label' }, [step.label]),
            ]),
            step.date ? el('span', { className: 'claim-timeline__date' }, [step.date]) : null,
        ])
    ));
}

function detailField(label, value) {
    if (!value) return null;
    return el('div', { className: 'claim-detail__field' }, [
        el('span', { className: 'claim-detail__field-label' }, [label]),
        el('span', { className: 'claim-detail__field-value' }, [value]),
    ]);
}

function renderDetailPanel(row) {
    const panel = el('aside', { className: 'card claim-detail' });
    if (!row) {
        panel.appendChild(el('p', { className: 'claim-detail__empty' }, ['Seleziona un cliente dall\'elenco.']));
        return panel;
    }

    const d = row.detail;

    panel.appendChild(el('div', { className: 'claim-detail__header' }, [
        el('span', { className: 'claim-detail__avatar' }, [row.avatarInitials]),
        el('div', { className: 'claim-detail__header-text' }, [
            el('span', { className: 'claim-detail__name' }, [d.name]),
            el('span', { className: 'claim-detail__type' }, [d.typeLabel]),
        ]),
    ]));

    const fields = [
        detailField('Volo', d.volo),
        detailField('Vettore', d.vettore),
        detailField('Disservizio', d.disservizio),
        detailField('Importo', d.importo),
        detailField('Fonte', d.fonte),
    ].filter(Boolean);
    if (fields.length > 0) {
        panel.appendChild(el('div', { className: 'claim-detail__fields' }, fields));
    }

    panel.appendChild(el('h4', { className: 'claim-detail__section-title' }, ['Percorso pratica']));
    panel.appendChild(timelineNode(d.timeline));

    if (d.suggestedAction) {
        panel.appendChild(el('div', { className: 'claim-detail__suggestion' }, [
            el('span', { className: 'claim-detail__suggestion-title' }, ['Prossima azione suggerita']),
            el('p', { className: 'claim-detail__suggestion-body' }, [d.suggestedAction]),
        ]));
    }

    const actions = el('div', { className: 'claim-detail__actions' }, [
        el('a', { className: 'btn btn--secondary', href: d.editHref }, ['Modifica']),
    ]);
    if (d.openCaseHref) {
        actions.appendChild(el('a', { className: 'btn btn--primary', href: d.openCaseHref }, ['Apri pratica']));
    }
    panel.appendChild(actions);

    return panel;
}

function buildFilterBar(data, applyFilter) {
    const tabsContainer = el('div', { className: 'data-table__filter-tabs' });
    const moveIndicator = createSlidingIndicator(tabsContainer, 'data-table__filter-indicator');

    let activeValue = data.filterTabs[0]?.value ?? null;

    const buttons = data.filterTabs.map((tab) => {
        const button = el(
            'button',
            {
                type: 'button',
                className: 'data-table__filter-tab' + (tab.value === activeValue ? ' is-active' : ''),
                onClick: (e) => {
                    activeValue = tab.value;
                    tabsContainer.querySelectorAll('.data-table__filter-tab').forEach((b) => b.classList.remove('is-active'));
                    e.currentTarget.classList.add('is-active');
                    moveIndicator(e.currentTarget);
                    applyFilter(activeValue, searchInput.value.trim().toLowerCase());
                },
            },
            [`${tab.label} `, el('span', { className: 'data-table__filter-count' }, [String(tab.count)])]
        );
        tabsContainer.appendChild(button);
        return button;
    });

    requestAnimationFrame(() => moveIndicator(tabsContainer.querySelector('.is-active') || buttons[0]));

    const searchInput = el('input', {
        type: 'text',
        className: 'data-table__filter-search',
        placeholder: 'Nome, email, volo...',
    });
    searchInput.addEventListener('input', () => applyFilter(activeValue, searchInput.value.trim().toLowerCase()));

    const searchWrap = el('div', { className: 'data-table__filter-search-wrap' }, [searchInput]);

    return el('div', { className: 'data-table__toolbar' }, [tabsContainer, searchWrap]);
}

function renderClaimTable(data) {
    const rows = data.rows || [];

    const headerActions = el('div', { className: 'claim-table__actions' }, [
        data.exportHref ? el('a', { className: 'btn btn--secondary', href: data.exportHref }, ['Esporta']) : null,
        data.createHref ? el('a', { className: 'btn btn--primary', href: data.createHref }, [data.createLabel || 'Nuovo']) : null,
    ].filter(Boolean));

    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, [data.title || '']),
        headerActions,
    ]);

    const statsGrid = el('div', { className: 'stat-grid' }, (data.stats || []).map(renderComponent).filter(Boolean));

    const tbody = el('tbody', {});
    const detailContainer = el('div', { className: 'claim-table__detail-slot' });

    function selectRow(row, rowEl) {
        tbody.querySelectorAll('tr').forEach((tr) => tr.classList.remove('is-selected'));
        if (rowEl) rowEl.classList.add('is-selected');
        detailContainer.textContent = '';
        detailContainer.appendChild(renderDetailPanel(row));
    }

    function renderRows(filterValue, query) {
        tbody.textContent = '';
        const filtered = rows.filter((row) => {
            const matchesBucket = !filterValue || filterValue === 'tutti' || row.filterBucket === filterValue;
            const haystack = [row.name, row.email, row.flightLabel, row.disservizioLabel].filter(Boolean).join(' ').toLowerCase();
            const matchesQuery = !query || haystack.includes(query);
            return matchesBucket && matchesQuery;
        });

        if (filtered.length === 0) {
            tbody.appendChild(el('tr', {}, [el('td', { className: 'claim-table__empty', colspan: '5' }, [data.emptyMessage])]));
            return;
        }

        filtered.forEach((row, i) => {
            const tr = buildRow(row, (r) => selectRow(r, tr));
            if (i === 0) tr.classList.add('is-selected');
            tbody.appendChild(tr);
        });
    }

    const filterBar = data.filterTabs.length > 0
        ? buildFilterBar(data, renderRows)
        : null;

    renderRows(data.filterTabs[0]?.value ?? null, '');
    selectRow(rows[0] || null, tbody.querySelector('tr'));

    const table = el('table', { className: 'data-table__table' }, [
        el('thead', {}, [
            el('tr', {}, [
                el('th', {}, ['Cliente']),
                el('th', {}, ['Volo e disservizio']),
                el('th', {}, ['Stato pratica']),
                el('th', {}, ['Documenti']),
                el('th', { className: 'claim-table__amount-cell' }, ['Importo']),
            ]),
        ]),
        tbody,
    ]);

    const tableCard = el('div', { className: 'data-table__card' }, [table]);
    const mainCol = el('div', { className: 'claim-table__main' }, [filterBar, tableCard].filter(Boolean));

    return el('div', {}, [
        header,
        statsGrid,
        el('div', { className: 'claim-table__layout' }, [mainCol, detailContainer]),
    ]);
}

registerComponent('claim-table', renderClaimTable);
