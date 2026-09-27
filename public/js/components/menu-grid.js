import { el, staggerReveal, createSlidingIndicator } from '../dom.js';
import { icon } from '../icons.js';
import { registerComponent } from './registry.js';

function initials(label) {
    return (label || '').trim().slice(0, 2).toUpperCase();
}

function buildCard(item) {
    const color = item.sectionColor || 'petrol';

    return el(
        'button',
        {
            type: 'button',
            className: 'menu-grid__item',
            onClick: () => window.unoSubmitPrompt && window.unoSubmitPrompt(item.prompt),
        },
        [
            el('div', { className: 'menu-grid__item-top' }, [
                el('div', { className: 'menu-grid__badge', style: `background: var(--color-${color});` }, [
                    initials(item.label),
                ]),
                item.count != null ? el('div', { className: 'menu-grid__count' }, [item.count]) : null,
            ]),
            el('div', { className: 'menu-grid__label' }, [item.label]),
            el('div', { className: 'menu-grid__description' }, [item.description || '']),
            item.sectionLabel
                ? el('div', { className: 'menu-grid__item-footer' }, [
                      el('span', { className: 'menu-grid__item-tag' }, [item.sectionLabel]),
                      el('span', { className: 'menu-grid__item-arrow' }, [icon('arrow-right')]),
                  ])
                : null,
        ]
    );
}

/**
 * Riorganizzato in sezioni filtrabili (Sistema/CRM/Operativita/Vendite/
 * Contenuti - vedi ShowMenuTool::SECTIONS, l'unica fonte di questa
 * tassonomia) + ricerca libera per label/descrizione, invece della sola
 * griglia piatta di prima: a parita' di dati (nessuna voce nuova, solo
 * 'section'/'count' aggiunti al payload) diventa molto piu' rapido
 * orientarsi quando le voci di menu crescono oltre una manciata.
 */
function render(data) {
    const items = data.items || [];
    const sections = data.sections || [];

    let activeSection = null; // null = 'Tutte'
    let query = '';

    function matches(item) {
        if (activeSection !== null && item.section !== activeSection) return false;
        if (query !== '') {
            const haystack = `${item.label} ${item.description || ''}`.toLowerCase();
            if (!haystack.includes(query)) return false;
        }
        return true;
    }

    const grid = el('div', { className: 'menu-grid__grid' });
    const emptyState = el('p', { className: 'menu-grid__empty' }, ['Nessuna voce corrisponde ai filtri.']);
    emptyState.hidden = true;

    function renderGrid() {
        while (grid.firstChild) grid.removeChild(grid.firstChild);

        const visible = items.filter(matches).map(buildCard);
        visible.forEach((card) => grid.appendChild(card));
        staggerReveal(visible, 55);
        emptyState.hidden = visible.length > 0;
    }

    const tabDefs = [{ key: null, label: 'Tutte', color: null }, ...sections];
    const tabButtons = tabDefs.map((tab) =>
        el(
            'button',
            {
                type: 'button',
                className: 'menu-grid__tab',
                onClick: (e) => {
                    activeSection = tab.key;
                    tabButtons.forEach((btn, i) => btn.classList.toggle('is-active', tabDefs[i].key === activeSection));
                    moveTabIndicator(e.currentTarget);
                    renderGrid();
                },
            },
            [
                // Colore proprio della sezione, non cambia con lo stato
                // attivo/inattivo (a differenza del testo sotto) - e'
                // l'unica cosa che dice DAVVERO di quale sezione si
                // tratta, sbiancarlo sotto la pillola nera perderebbe
                // quel significato.
                tab.color ? el('span', { className: 'menu-grid__tab-dot', style: `background: var(--color-${tab.color});` }) : null,
                tab.label,
            ]
        )
    );
    tabButtons[0].classList.add('is-active');

    const tabsContainer = el('div', { className: 'menu-grid__tabs' }, tabButtons);
    // Pillola nera come elemento a se' che scorre verso il bottone
    // cliccato invece di ricolorare il bottone stesso (segnalato
    // dall'utente da uno screenshot - prima sembrava "cambiare colore
    // sul posto" perche' l'unico elemento visibile ERA il bottone).
    const moveTabIndicator = createSlidingIndicator(tabsContainer, 'menu-grid__tab-indicator');
    // Non tabButtons[0] fisso: se un click arriva prima che questo
    // requestAnimationFrame scatti (possibile, e' successo davvero in
    // test automatizzati rapidi), tabButtons[0] risposizionerebbe la
    // pillola sul tab sbagliato sopra a quello appena scelto. Si cerca
    // sempre il tab is-active AL MOMENTO in cui il frame scatta.
    requestAnimationFrame(() => moveTabIndicator(tabsContainer.querySelector('.is-active') || tabButtons[0]));

    const searchInput = el('input', {
        type: 'text',
        className: 'menu-grid__search',
        placeholder: 'Filtra voci...',
        onInput: (e) => {
            query = e.target.value.trim().toLowerCase();
            renderGrid();
        },
    });

    const toolbar = el('div', { className: 'menu-grid__toolbar' }, [
        el('div', { className: 'menu-grid__heading' }, [
            el('p', { className: 'menu-grid__eyebrow' }, [`Navigazione · ${items.length} voci`]),
            el('h2', { className: 'card__title' }, [data.title || 'Menu']),
        ]),
        el('div', { className: 'menu-grid__controls' }, [
            tabsContainer,
            el('div', { className: 'menu-grid__search-wrap' }, [icon('search'), searchInput]),
        ]),
    ]);

    renderGrid();

    // NON e' una .card: il contenuto (barra + griglia) vive direttamente
    // sullo sfondo grigio della pagina, come .hero__intro sopra la card
    // del prompt - un'altra card bianca qui intorno creava un "riquadro
    // dentro il riquadro" (segnalato dall'utente con uno screenshot). Le
    // singole .menu-grid__item restano le uniche vere superfici bianche,
    // come tessere di un cruscotto invece che contenuto dentro un'unica
    // card che le contiene tutte.
    return el('section', { className: 'menu-grid' }, [toolbar, grid, emptyState]);
}

registerComponent('menu-grid', render);
