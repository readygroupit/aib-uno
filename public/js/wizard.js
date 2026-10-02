import { el } from './dom.js';

/**
 * Contenuto "cosa posso fare?" (oggi scheda della Guida): due elenchi, entrambi costruiti dal server
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
function buildEntry(label, description) {
    const node = el('div', { className: 'wizard__entry' }, [
        el('div', { className: 'wizard__entry-label' }, [label]),
        el('div', { className: 'wizard__entry-desc' }, [description]),
    ]);

    return { node, haystack: (label + ' ' + description).toLowerCase() };
}

/**
 * Contenuto della scheda "Cosa posso fare" della Guida (vedi
 * onboarding.js): ricerca + elenchi, senza pannello/overlay propri - il
 * contenitore e' la Guida, unico punto di aiuto dell'app.
 */
export function buildFunctionsView(data) {
    const groups = data.groups || [];
    const allEntries = [];
    const body = el('div', { className: 'wizard__body' });

    groups.forEach((group) => {
        const entries = group.items.map((c) => buildEntry(c.label, c.description));
        allEntries.push(...entries);

        body.appendChild(
            el('h3', { className: 'wizard__section-title' }, [
                el('span', { className: 'wizard__section-dot wizard__section-dot--' + (group.dotColor || 'petrol') }),
                group.label,
            ])
        );
        body.appendChild(el('div', { className: 'wizard__list' }, entries.map((c) => c.node)));
    });

    if (groups.length === 0) {
        body.appendChild(el('p', { className: 'wizard__empty' }, ['Nessuna funzione disponibile con i permessi attuali.']));
    }

    const fieldHelp = (data.fieldHelp || []).map((f) => buildEntry(f.label, f.help));
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
        [...allEntries, ...fieldHelp].forEach((entry) => {
            entry.node.style.display = entry.haystack.includes(query) ? '' : 'none';
        });
    });

    return el('div', { className: 'guide-functions' }, [searchInput, body]);
}

/** @returns {Promise<?object>} i dati del componente 'wizard', o null se la richiesta fallisce. */
export function fetchFunctions(entity) {
    const query = entity ? '?entity=' + encodeURIComponent(entity) : '';

    return fetch('/wizard' + query, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((res) => res.json())
        .then((data) => (data.components || [])[0] || null)
        .catch(() => null);
}
