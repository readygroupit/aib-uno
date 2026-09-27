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
 */
export function mountComponents(root, components, staggerMs = 150) {
    const nodes = (components || []).map((componentData) => renderComponent(componentData)).filter(Boolean);
    nodes.forEach((node) => root.appendChild(node));
    staggerReveal(nodes, staggerMs);
}
