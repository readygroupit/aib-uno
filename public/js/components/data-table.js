import { registerComponent } from './registry.js';
import { el, createSlidingIndicator } from '../dom.js';
import { icon } from '../icons.js';
import { openModal } from '../modal.js';

function closeAllActionMenus() {
    document.querySelectorAll('.data-table__actions-menu.is-open').forEach((menu) => {
        menu.classList.remove('is-open');
    });
}

document.addEventListener('click', closeAllActionMenus);

// Etichetta italiana + colore per le 4 categorie di permesso (vedi
// ListPermissionsTool::CATEGORIES, l'unica fonte di questo vocabolario -
// qui solo la resa visiva). 'stage' invece e' testo libero per pacchetto
// (niente enum a DB, vedi memoria progetto) quindi non ha un vocabolario
// fisso da mappare: un piccolo classificatore a parole chiave italiane
// deduce il colore, non serve configurare nulla lato server per
// ottenerlo su un pacchetto nuovo.
const CATEGORY_LABELS = { view: 'Lettura', create: 'Creazione', edit: 'Modifica', delete: 'Eliminazione' };
const CATEGORY_VARIANTS = { view: 'info', create: 'success', edit: 'warning', delete: 'danger' };

// Stessa tassonomia di App\Support\Sections (menu + catalogo package),
// mappata sulle 6 varianti .badge--* gia' esistenti invece di 5 colori
// nuovi da aggiungere al CSS: gli abbinamenti sono gia' vicini per tono
// (moss/success entrambi verdi, brass/warning entrambi ambra, ecc.).
const SECTION_LABELS = { sistema: 'Sistema', crm: 'CRM', operativita: 'Operativita', vendite: 'Vendite', contenuti: 'Contenuti' };
const SECTION_VARIANTS = { sistema: 'neutral', crm: 'success', operativita: 'accent', vendite: 'warning', contenuti: 'info' };

const STAGE_DANGER_WORDS = ['annullat', 'pers', 'rifiutat', 'scadut', 'eliminat', 'esaurit'];
const STAGE_SUCCESS_WORDS = ['complet', 'vint', 'convertit', 'pagat', 'confermat', 'evas', 'pubblicat', 'inviat', 'in vendita', 'chius', 'attiv', 'installat'];
const STAGE_WARNING_WORDS = ['bozza', 'attesa', 'in corso', 'sospes', 'programmat', 'nuov'];

function stageVariant(value) {
    const v = value.toLowerCase();
    if (STAGE_DANGER_WORDS.some((w) => v.includes(w))) return 'danger';
    if (STAGE_SUCCESS_WORDS.some((w) => v.includes(w))) return 'success';
    // Prefisso generico "da ..." (da fare, da installare, da confermare,
    // da assegnare...) invece di elencare ogni frase possibile una per
    // una: in italiano "da X" e' quasi sempre "in sospeso, da fare".
    if (v.startsWith('da ') || STAGE_WARNING_WORDS.some((w) => v.includes(w))) return 'warning';
    return 'info';
}

/**
 * Qualunque colonna con key 'stage'/'category'/'section' si rende come
 * badge colorato invece di testo semplice - per convenzione di nome, non
 * serve un flag esplicito nella config della colonna (stessa idea gia'
 * provata per lo stagger del menu: una convenzione di naming battuta in
 * tutto il progetto vale piu' di un flag da ricordare per ogni tabella
 * nuova). Tre chiavi diverse (non tutte 'category') perche' i vocabolari
 * sono diversi e andrebbero in conflitto sotto la stessa chiave: i
 * permessi usano 'category' (view/create/edit/delete), i package usano
 * 'section' (stessa tassonomia del menu, App\Support\Sections).
 */
function buildCellContent(column, value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    if (column.key === 'category' && CATEGORY_VARIANTS[value]) {
        return el('span', { className: `badge badge--${CATEGORY_VARIANTS[value]}` }, [CATEGORY_LABELS[value] || String(value)]);
    }

    if (column.key === 'section' && SECTION_VARIANTS[value]) {
        return el('span', { className: `badge badge--${SECTION_VARIANTS[value]}` }, [SECTION_LABELS[value] || String(value)]);
    }

    if (column.key === 'stage') {
        return el('span', { className: `badge badge--${stageVariant(String(value))}` }, [String(value)]);
    }

    return String(value);
}

function detailRow(label, valueNode) {
    return el('div', { className: 'data-table__detail-row' }, [
        el('span', { className: 'data-table__detail-label' }, [label]),
        el('div', { className: 'data-table__detail-value' }, [valueNode]),
    ]);
}

function fieldList(labels) {
    if (!labels || labels.length === 0) {
        return el('span', { className: 'data-table__detail-empty' }, ['Nessuno']);
    }
    return el('div', { className: 'data-table__detail-tags' }, labels.map((l) => el('span', { className: 'tag' }, [l])));
}

/**
 * Il dettaglio di un package (descrizione/sezione/dipendenze/campi) non
 * ha una pagina propria - PackageCatalogService::list() manda gia' tutto
 * il necessario dentro ogni riga (vedi entitiesSummary() li'), qui si
 * limita a comporlo in un modal.js invece di fare un'altra richiesta.
 */
function openPackageDetailModal(row) {
    const body = [
        el('p', { className: 'data-table__detail-description' }, [row.description || 'Nessuna descrizione.']),
        detailRow('Sezione', el('span', {}, [SECTION_LABELS[row.section] || 'Non categorizzato'])),
        detailRow('Stato', el('span', {}, [row.installed ? 'Installato' : 'Da installare'])),
        detailRow('Dipende da', fieldList(row.dependsOn)),
    ];

    (row.entities || []).forEach((entity) => {
        body.push(el('h4', { className: 'data-table__detail-entity' }, [`${entity.key} (tabella ${entity.table})`]));
        body.push(detailRow('Campi sempre inclusi', fieldList(entity.baseFields)));
        body.push(detailRow('Campi negoziabili', fieldList(entity.optionalFields)));
    });

    openModal({ title: row.label, body, tone: 'info' });
}

/**
 * Un'azione e' quasi sempre un link (href) - 'kind: detail' e' l'unica
 * eccezione finora (il dettaglio dei package, vedi openPackageDetailModal
 * sopra): niente pagina/route dedicata, solo dati gia' presenti sulla
 * riga stessa, quindi un bottone che apre modal.js invece di navigare.
 */
function buildActionElement(row, action, className) {
    if (action.kind === 'detail') {
        return el(
            'button',
            { type: 'button', className, onClick: () => openPackageDetailModal(row) },
            [action.label]
        );
    }

    return el('a', { className, href: action.href.replace('{id}', row.id ?? '') }, [action.label]);
}

function buildActionsCell(row, actions) {
    const cell = el('td', { className: 'data-table__actions-col' });

    if (!actions || actions.length === 0) {
        return cell;
    }

    if (actions.length === 1) {
        cell.appendChild(buildActionElement(row, actions[0], 'btn btn--small btn--secondary'));
        return cell;
    }

    const menu = el(
        'div',
        { className: 'data-table__actions-menu' },
        actions.map((action) => buildActionElement(row, action, 'data-table__actions-menu-item'))
    );

    const trigger = el(
        'button',
        {
            type: 'button',
            className: 'btn btn--ghost btn--small data-table__actions-trigger',
            title: 'Azioni',
            onClick: (e) => {
                e.stopPropagation();
                const wasOpen = menu.classList.contains('is-open');
                closeAllActionMenus();
                if (!wasOpen) {
                    menu.classList.add('is-open');
                }
            },
        },
        [icon('ellipsis-vertical')]
    );

    cell.appendChild(el('div', { className: 'data-table__actions-dropdown' }, [trigger, menu]));

    return cell;
}

function buildHeader(data) {
    if (!data.title && !data.createHref) {
        return null;
    }

    const children = [];
    if (data.title) {
        children.push(el('h2', { className: 'card__title' }, [data.title]));
    }
    if (data.createHref) {
        children.push(el('a', { className: 'btn btn--small btn--primary', href: data.createHref }, [data.createLabel || 'Nuovo']));
    }

    return el('div', { className: 'data-table__header' }, children);
}

function buildRow(data, row, hasActions) {
    const tr = el('tr', {}, data.columns.map((column) => el('td', {}, [buildCellContent(column, row[column.key])])));
    if (hasActions) {
        tr.appendChild(buildActionsCell(row, data.actions));
    }
    return tr;
}

function rowMatches(data, row, activeTab, query) {
    if (data.filterField && activeTab !== null && String(row[data.filterField] ?? '') !== activeTab) {
        return false;
    }
    if (query !== '') {
        const haystack = data.columns.map((c) => String(row[c.key] ?? '')).join(' ').toLowerCase();
        if (!haystack.includes(query)) {
            return false;
        }
    }
    return true;
}

/**
 * Barra di filtri opzionale (tab colorati + ricerca libera), tutto lato
 * client - pensata per tabelle senza paginazione server (poche decine di
 * righe, vedi ListPermissionsTool, il primo e finora unico chiamante).
 * Le altre liste non passano filterTabs/searchable e restano identiche a
 * prima: nessuna di queste funzioni viene nemmeno chiamata.
 */
function buildFilterBar(data, hasActions, tbody) {
    let activeTab = null;
    let query = '';

    function applyFilter() {
        while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

        const visible = data.rows.filter((row) => rowMatches(data, row, activeTab, query));
        if (visible.length === 0) {
            const colspan = data.columns.length + (hasActions ? 1 : 0);
            const emptyRow = el('tr', {}, [
                el('td', { colspan: String(colspan), className: 'data-table__empty' }, ['Nessuna voce corrisponde ai filtri.']),
            ]);
            tbody.appendChild(emptyRow);
            return;
        }

        visible.forEach((row) => tbody.appendChild(buildRow(data, row, hasActions)));
    }

    const children = [];

    if (Array.isArray(data.filterTabs) && data.filterTabs.length > 0) {
        const tabButtons = data.filterTabs.map((tab) =>
            el(
                'button',
                {
                    type: 'button',
                    className: 'data-table__filter-tab',
                    onClick: (e) => {
                        activeTab = tab.key;
                        tabButtons.forEach((btn, i) => btn.classList.toggle('is-active', data.filterTabs[i].key === activeTab));
                        moveTabIndicator(e.currentTarget);
                        applyFilter();
                    },
                },
                [
                    tab.color ? el('span', { className: 'data-table__filter-dot', style: `background: var(--color-${tab.color});` }) : null,
                    tab.label,
                ]
            )
        );
        tabButtons[0].classList.add('is-active');
        const tabsContainer = el('div', { className: 'data-table__filter-tabs' }, tabButtons);
        // Stessa pillola scorrevole del menu (createSlidingIndicator in
        // dom.js) - qui pero' l'aspetto resta chiaro/rialzato (vedi CSS),
        // non nero: il contesto e' gia' dentro una card bianca, non sullo
        // sfondo grigio della pagina.
        const moveTabIndicator = createSlidingIndicator(tabsContainer, 'data-table__filter-indicator');
        // Non tabButtons[0] fisso - vedi la stessa nota in menu-grid.js:
        // un click prima che questo frame scatti risposizionerebbe la
        // pillola sul tab sbagliato. Si cerca il tab is-active al momento.
        requestAnimationFrame(() => moveTabIndicator(tabsContainer.querySelector('.is-active') || tabButtons[0]));
        children.push(tabsContainer);
    }

    if (data.searchable) {
        const input = el('input', {
            type: 'text',
            className: 'data-table__filter-search',
            placeholder: 'Cerca...',
            onInput: (e) => {
                query = e.target.value.trim().toLowerCase();
                applyFilter();
            },
        });
        children.push(el('div', { className: 'data-table__filter-search-wrap' }, [icon('search'), input]));
    }

    return { bar: el('div', { className: 'data-table__filter-bar' }, children), applyFilter };
}

function buildPagination(data) {
    if (!(data.totalPages > 1)) return null;

    const prev = data.page > 1
        ? el('a', { href: `?page=${data.page - 1}` }, ['‹ precedente'])
        : el('span', { className: 'is-disabled' }, ['‹ precedente']);
    const next = data.page < data.totalPages
        ? el('a', { href: `?page=${data.page + 1}` }, ['successiva ›'])
        : el('span', { className: 'is-disabled' }, ['successiva ›']);

    return el('div', { className: 'data-table__pagination' }, [
        el('span', {}, [`${data.total} risultati`]),
        el('div', { className: 'data-table__pagination-nav' }, [
            prev,
            el('span', {}, [`Pagina ${data.page} di ${data.totalPages}`]),
            next,
        ]),
    ]);
}

/**
 * Con filtri (tab/ricerca) il titolo e la barra filtri escono dalla card
 * bianca e vivono sul grigio della pagina, come il menu - solo la
 * tabella vera resta boxata. Segnalato dall'utente da uno screenshot:
 * prima l'intera barra filtri (sfondo canvas) stava DENTRO la card
 * bianca, un "riquadro grigio dentro il riquadro bianco" della stessa
 * famiglia del bug gia' risolto nel menu. Senza filtri (la stragrande
 * maggioranza delle liste) il comportamento resta quello di sempre, una
 * sola card con titolo+tabella dentro - nessuna di queste liste e'
 * toccata da questo ramo.
 */
function renderDataTable(data) {
    const header = buildHeader(data);

    if (!data.rows || data.rows.length === 0) {
        const section = el('section', { className: 'card data-table' });
        if (header) section.appendChild(header);
        section.appendChild(el('p', { className: 'data-table__empty' }, [data.emptyMessage || 'Nessun risultato.']));
        return section;
    }

    const hasActions = Array.isArray(data.actions) && data.actions.length > 0;
    const hasFilters = (Array.isArray(data.filterTabs) && data.filterTabs.length > 0) || data.searchable;

    const headRow = el('tr', {}, data.columns.map((column) => el('th', {}, [column.label])));
    if (hasActions) {
        headRow.appendChild(el('th', { className: 'data-table__actions-col' }));
    }

    const tbody = el('tbody');
    let filterBar = null;

    if (hasFilters) {
        const built = buildFilterBar(data, hasActions, tbody);
        filterBar = built.bar;
        built.applyFilter();
    } else {
        for (const row of data.rows) {
            tbody.appendChild(buildRow(data, row, hasActions));
        }
    }

    const table = el('table', { className: 'data-table__table' }, [el('thead', {}, [headRow]), tbody]);
    const scroll = el('div', { className: 'data-table__scroll' }, [table]);
    const pagination = buildPagination(data);

    if (hasFilters) {
        const toolbar = el('div', { className: 'data-table__toolbar' }, [header, filterBar].filter(Boolean));
        const card = el('div', { className: 'card data-table__card' }, [scroll, pagination].filter(Boolean));

        return el('section', { className: 'data-table' }, [toolbar, card]);
    }

    const section = el('section', { className: 'card data-table' }, [header, scroll, pagination].filter(Boolean));

    return section;
}

registerComponent('data-table', renderDataTable);
