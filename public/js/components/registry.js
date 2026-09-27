import { staggerReveal } from '../dom.js';

const renderers = new Map();

export function registerComponent(type, renderFn) {
    renderers.set(type, renderFn);
}

export function renderComponent(data) {
    const renderFn = renderers.get(data.type);
    if (!renderFn) {
        console.warn(`Nessun renderer JS per il componente "${data.type}"`);
        return null;
    }
    return renderFn(data);
}

/**
 * Monta una lista di componenti dentro root, uno alla volta in ordine,
 * con una dissolvenza scaglionata (non tutti insieme) - unica versione
 * di questa logica, usata sia dal caricamento pagina (app.js) sia dalle
 * risposte del prompt (hero.js). 90ms (il valore di partenza) si e'
 * rivelato troppo ravvicinato per notare davvero la cascata (segnalato
 * dall'utente) - 150ms tra un componente e l'altro.
 *
 * Le 'stat-box' consecutive finiscono raggruppate dentro un unico
 * contenitore '.stat-grid' (classe gia' presente in stat-box.css, prima
 * inutilizzata: senza questo raggruppamento ogni card finiva impilata a
 * piena larghezza invece che in griglia - il cruscotto home ne mostra
 * fino a 6 insieme, vedi ShowHomeDashboardTool). Lo scaglionamento resta
 * per singola card (non per l'intero gruppo): si passa a staggerReveal()
 * l'elenco piatto dei nodi veri, non i contenitori.
 */
export function mountComponents(root, components, staggerMs = 150) {
    const topLevelNodes = [];
    const staggerNodes = [];
    let currentStatGroup = null;

    (components || []).forEach((componentData) => {
        const node = renderComponent(componentData);
        if (!node) return;

        if (componentData.type === 'stat-box') {
            if (!currentStatGroup) {
                currentStatGroup = document.createElement('div');
                currentStatGroup.className = 'stat-grid';
                topLevelNodes.push(currentStatGroup);
            }
            currentStatGroup.appendChild(node);
        } else {
            currentStatGroup = null;
            topLevelNodes.push(node);
        }

        staggerNodes.push(node);
    });

    topLevelNodes.forEach((node) => root.appendChild(node));
    staggerReveal(staggerNodes, staggerMs);
}
