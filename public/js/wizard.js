import { el } from './dom.js';

/**
 * Modale "cosa posso fare?": due elenchi, entrambi costruiti dal server
 * in base a cosa e' davvero installato/consentito in questo momento
 * (mai testo statico scritto qui) - le funzioni disponibili (dal
 * PromptToolRegistry, stesso principio di ShowMenuTool ma qui senza
 * filtrare su menuLabel: anche "modifica contatto" merita una riga anche
 * se non e' una voce di menu cliccabile) e le spiegazioni dei campi
 * dell'entita' attualmente in vista che il manifest del pacchetto marca
 * come bisognosi di chiarimento (vedi 'help' in packages/*\/package.php
 * - solo i campi non ovvi, non tutti). Un solo <input> di ricerca filtra
 * entrambi gli elenchi lato client: sono gia' tutti in memoria, non ha
 * senso un giro server per ogni lettera digitata.
 */
let overlayEl = null;

function onKeydown(e) {
    if (e.key === 'Escape') {
        closeWizard();
    }
}

function closeWizard() {
    if (!overlayEl) return;
    overlayEl.remove();
    overlayEl = null;
    document.removeEventListener('keydown', onKeydown);
}

function buildEntry(label, description) {
    const node = el('div', { className: 'wizard__entry' }, [
        el('div', { className: 'wizard__entry-label' }, [label]),
        el('div', { className: 'wizard__entry-desc' }, [description]),
    ]);

    return { node, haystack: (label + ' ' + description).toLowerCase() };
}

function renderWizard(data) {
    closeWizard();

    const capabilities = (data.capabilities || []).map((c) => buildEntry(c.label, c.description));
    const fieldHelp = (data.fieldHelp || []).map((f) => buildEntry(f.label, f.help));

    const body = el('div', { className: 'wizard__body' });

    body.appendChild(el('h3', { className: 'wizard__section-title' }, ['Cosa puoi fare qui']));
    body.appendChild(
        capabilities.length
            ? el('div', { className: 'wizard__list' }, capabilities.map((c) => c.node))
            : el('p', { className: 'wizard__empty' }, ['Nessuna funzione disponibile con i permessi attuali.'])
    );

    if (fieldHelp.length) {
        body.appendChild(el('h3', { className: 'wizard__section-title' }, ['Campi da conoscere']));
        body.appendChild(el('div', { className: 'wizard__list' }, fieldHelp.map((f) => f.node)));
    }

    const searchInput = el('input', {
        type: 'text',
        className: 'wizard__search',
        placeholder: 'Cerca cosa puoi fare...',
    });
    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        [...capabilities, ...fieldHelp].forEach((entry) => {
            entry.node.style.display = entry.haystack.includes(query) ? '' : 'none';
        });
    });

    const panel = el('div', { className: 'wizard__panel' }, [
        el('div', { className: 'wizard__header' }, [
            el('img', { src: window.UNO_LOGO_SQUARE || window.UNO_LOGO, alt: '', className: 'wizard__logo' }),
            el('h2', { className: 'wizard__title' }, [data.title || 'Cosa posso fare?']),
            el('button', { type: 'button', className: 'wizard__close', title: 'Chiudi', onClick: closeWizard }, ['×']),
        ]),
        searchInput,
        body,
    ]);

    overlayEl = el(
        'div',
        {
            className: 'wizard-overlay',
            onClick: (e) => {
                if (e.target === overlayEl) closeWizard();
            },
        },
        [panel]
    );
    document.body.appendChild(overlayEl);
    document.addEventListener('keydown', onKeydown);
    searchInput.focus();
}

export function openWizard(entity) {
    const query = entity ? '?entity=' + encodeURIComponent(entity) : '';

    fetch('/wizard' + query, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((res) => res.json())
        .then((data) => {
            const wizardData = (data.components || [])[0];
            if (wizardData) {
                renderWizard(wizardData);
            }
        })
        .catch(() => {});
}
