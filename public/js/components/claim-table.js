import { registerComponent, renderComponent } from './registry.js';
import { el, createSlidingIndicator } from '../dom.js';
import { icon } from '../icons.js';

function avatar(row, size) {
    const className = `claim-avatar claim-avatar--${size} ` + (row.isCompany ? 'claim-avatar--company' : 'claim-avatar--personal');
    return el('span', { className }, [row.avatarInitials]);
}

function stageBadge(label, variant) {
    const v = variant || 'neutral';
    return el('span', { className: `claim-table__stage badge badge--${v}` }, [
        el('span', { className: `claim-table__stage-dot claim-table__stage-dot--${v}` }),
        label,
    ]);
}

function docsProgress(total, complete) {
    if (total === 0) {
        return el('span', { className: 'claim-table__docs-empty' }, ['—']);
    }

    const done = complete === total;
    const segments = [];
    for (let i = 0; i < total; i++) {
        const state = i >= complete ? '' : done ? ' is-complete' : ' is-partial';
        segments.push(el('span', { className: 'claim-table__docs-seg' + state }));
    }

    return el('div', { className: 'claim-table__docs' }, [
        el('span', { className: 'claim-table__docs-label' }, [done ? 'Completi' : `${complete} di ${total}`]),
        el('div', { className: 'claim-table__docs-bar' }, segments),
    ]);
}

function buildRow(row, onSelect) {
    const subtitle = [row.email, row.phone].filter(Boolean).join(' · ');

    const cells = [
        el('span', { className: 'claim-table__cell-customer' }, [
            avatar(row, 'md'),
            el('span', { className: 'claim-table__customer-text' }, [
                el('span', { className: 'claim-table__customer-name-row' }, [
                    el('span', { className: 'claim-table__customer-name' }, [row.name]),
                    row.companyTag ? el('span', { className: 'badge badge--warning claim-table__biz-tag' }, [row.companyTag]) : null,
                ]),
                el('span', { className: 'claim-table__customer-sub' }, [subtitle]),
            ]),
        ]),
        el('span', { className: 'claim-table__flight' }, [
            el('span', { className: 'claim-table__flight-route' }, [row.flightLabel || '—']),
            row.disservizioLabel ? el('span', { className: 'claim-table__flight-sub' }, [row.disservizioLabel]) : null,
        ]),
        el('span', { className: 'claim-table__stage-cell' }, [row.stageLabel ? stageBadge(row.stageLabel, row.stageVariant) : '—']),
        docsProgress(row.docsTotal, row.docsComplete),
        el('span', { className: 'claim-table__amount' }, [
            el('span', { className: 'claim-table__amount-value' }, [row.amountValue || '—']),
            row.amountCaption ? el('span', { className: 'claim-table__amount-caption' }, [row.amountCaption]) : null,
        ]),
    ];

    const rowEl = el('button', {
        type: 'button',
        className: 'claim-table__row claim-table__row-grid',
        onClick: () => onSelect(row),
    }, cells);
    rowEl.dataset.id = String(row.id);

    return rowEl;
}

function timelineNode(steps) {
    return el('div', { className: 'claim-timeline' }, steps.map((step) => {
        const className = ['claim-timeline__step', 'claim-timeline__step--' + step.state, step.danger ? 'claim-timeline__step--danger' : null]
            .filter(Boolean).join(' ');
        return el('div', { className }, [
            el('span', { className: 'claim-timeline__dot' }),
            el('span', { className: 'claim-timeline__label' }, [step.label]),
            step.date ? el('span', { className: 'claim-timeline__date' }, [step.date]) : null,
        ]);
    }));
}

function detailField(label, value) {
    if (!value) return null;
    return el('div', { className: 'claim-detail__field' }, [
        el('span', { className: 'claim-detail__field-label' }, [label]),
        el('span', { className: 'claim-detail__field-value' }, [value]),
    ]);
}

function renderDetailPanel(row, onClose) {
    const panel = el('aside', { className: 'card claim-detail' });
    if (!row) {
        panel.appendChild(el('p', { className: 'claim-detail__empty' }, ['Seleziona un cliente dall\'elenco.']));
        return panel;
    }

    const d = row.detail;

    panel.appendChild(el('div', { className: 'claim-detail__header' }, [
        avatar(row, 'lg'),
        el('div', { className: 'claim-detail__header-text' }, [
            el('span', { className: 'claim-detail__name' }, [d.name]),
            el('span', { className: 'claim-detail__type' }, [d.typeLabel]),
        ]),
        el('button', {
            type: 'button',
            className: 'claim-detail__close',
            onClick: onClose,
            'aria-label': 'Chiudi dettaglio',
        }, [icon('close')]),
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
    const tabsContainer = el('div', { className: 'claim-table__tabs' });
    const moveIndicator = createSlidingIndicator(tabsContainer, 'claim-table__tabs-indicator');

    let activeValue = data.filterTabs[0]?.value ?? null;

    const buttons = data.filterTabs.map((tab) => {
        const button = el(
            'button',
            {
                type: 'button',
                className: 'claim-table__tab' + (tab.value === activeValue ? ' is-active' : ''),
                onClick: (e) => {
                    activeValue = tab.value;
                    tabsContainer.querySelectorAll('.claim-table__tab').forEach((b) => b.classList.remove('is-active'));
                    e.currentTarget.classList.add('is-active');
                    moveIndicator(e.currentTarget);
                    applyFilter(activeValue, searchInput.value.trim().toLowerCase());
                },
            },
            [`${tab.label} `, el('span', { className: 'claim-table__tab-count' }, [String(tab.count)])]
        );
        tabsContainer.appendChild(button);
        return button;
    });

    requestAnimationFrame(() => moveIndicator(tabsContainer.querySelector('.is-active') || buttons[0]));

    const searchInput = el('input', {
        type: 'text',
        placeholder: 'Nome, email, volo...',
    });
    searchInput.addEventListener('input', () => applyFilter(activeValue, searchInput.value.trim().toLowerCase()));

    const searchWrap = el('label', { className: 'claim-table__search' }, [
        icon('search'),
        searchInput,
    ]);

    return el('div', { className: 'claim-table__toolbar' }, [tabsContainer, searchWrap]);
}

function renderClaimTable(data) {
    const rows = data.rows || [];
    let selectedId = rows[0]?.id ?? null;
    let closed = false;

    const headerActions = el('div', { className: 'claim-table__actions' }, [
        data.exportHref ? el('a', { className: 'btn btn--secondary', href: data.exportHref }, ['Esporta']) : null,
        data.createHref ? el('a', { className: 'btn btn--primary', href: data.createHref }, [data.createLabel || 'Nuovo']) : null,
    ].filter(Boolean));

    const header = el('div', { className: 'claim-table__header' }, [
        el('h1', { className: 'claim-table__title' }, [data.title || '']),
        headerActions,
    ]);

    const statsGrid = el('div', { className: 'stat-grid' }, (data.stats || []).map(renderComponent).filter(Boolean));

    const body = el('div', { className: 'claim-table__body' });
    const detailContainer = el('div', { className: 'claim-table__detail-slot' });

    function renderDetail() {
        detailContainer.textContent = '';
        if (closed) {
            return;
        }
        const row = rows.find((r) => r.id === selectedId) || null;
        detailContainer.appendChild(renderDetailPanel(row, () => {
            closed = true;
            renderDetail();
        }));
    }

    function selectRow(row) {
        selectedId = row.id;
        closed = false;
        body.querySelectorAll('.claim-table__row').forEach((rowEl) => {
            rowEl.classList.toggle('is-selected', rowEl.dataset.id === String(row.id));
        });
        renderDetail();
    }

    function renderRows(filterValue, query) {
        body.textContent = '';
        const filtered = rows.filter((row) => {
            const matchesBucket = !filterValue || filterValue === 'tutti' || row.filterBucket === filterValue;
            const haystack = [row.name, row.email, row.flightLabel, row.disservizioLabel].filter(Boolean).join(' ').toLowerCase();
            const matchesQuery = !query || haystack.includes(query);
            return matchesBucket && matchesQuery;
        });

        if (filtered.length === 0) {
            body.appendChild(el('div', { className: 'claim-table__empty' }, [data.emptyMessage]));
            selectedId = null;
            renderDetail();
            return;
        }

        if (!filtered.some((r) => r.id === selectedId)) {
            selectedId = filtered[0].id;
            closed = false;
        }

        filtered.forEach((row) => {
            const rowEl = buildRow(row, selectRow);
            if (row.id === selectedId) {
                rowEl.classList.add('is-selected');
            }
            body.appendChild(rowEl);
        });

        renderDetail();
    }

    const filterBar = data.filterTabs.length > 0 ? buildFilterBar(data, renderRows) : null;

    const head = el('div', { className: 'claim-table__row-grid claim-table__head' }, [
        el('span', {}, ['Cliente']),
        el('span', {}, ['Volo e disservizio']),
        el('span', {}, ['Stato pratica']),
        el('span', {}, ['Documenti']),
        el('span', { className: 'claim-table__amount-head' }, ['Importo']),
    ]);

    renderRows(data.filterTabs[0]?.value ?? null, '');

    const panel = el('div', { className: 'claim-table__panel' }, [filterBar, head, body].filter(Boolean));
    const mainCol = el('div', { className: 'claim-table__main' }, [panel]);

    return el('div', {}, [
        header,
        statsGrid,
        el('div', { className: 'claim-table__layout' }, [mainCol, detailContainer]),
    ]);
}

registerComponent('claim-table', renderClaimTable);
