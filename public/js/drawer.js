import { el } from './dom.js';
import { icon } from './icons.js';
import { mountComponents } from './components/registry.js';
import { toast, reloadCurrentScreen } from './accounts-ui.js';
import { openInPanel } from './components/hero.js';
import { guideParts } from './guide.js';

/**
 * Il pannello laterale: gli elementi "figli" di una pagina (una riga del
 * preventivo, una fase o un ruolo del processo, un referente del
 * cliente...) si aprono qui, sopra la pagina che resta sotto, invece che
 * in una schermata nuova. Il form e' lo stesso della sua pagina (stessi
 * campi, stesso salvataggio): salvato, il pannello si chiude e la pagina
 * sotto si aggiorna (resta sulla scheda in cui si era). Con un errore il
 * pannello resta aperto sul campo da correggere. Da telefono occupa tutto
 * lo schermo.
 *
 * Quali indirizzi si aprono qui: DRAWER_PATHS e ogni "nuovo" aperto da
 * una pagina (isDrawerUrl), un posto solo.
 */
const DRAWER_PATHS = [
    /^\/preventivi\/righe\/(nuovo|\d+)$/,
    /^\/tipi-commessa\/(fasi|ruoli|date|consegne)\/(nuovo|\d+)$/,
    /^\/commesse\/(squadra|date|consegne|modifiche)\/(nuovo|\d+)$/,
    /^\/clienti\/(referenti|squadra|contatti|diario|profilo)\/(nuovo|\d+)$/,
    /^\/clienti\/\d+\/dati-mancanti$/,
    /^\/(spese|appuntamenti)\/\d+$/,
];

// Un "nuovo" aperto da una pagina (con i dati gia' pronti nell'indirizzo,
// es. /spese/nuovo?work_order_id=5) si fa nel pannello; queste invece sono
// pagine a se', dove conviene arrivare (il progetto o il preventivo nuovo).
const NEW_AS_PAGE = [/^\/commesse\/nuovo$/, /^\/preventivi\/nuovo$/, /^\/fatture\/nuovo$/];

let current = null;

export function isDrawerUrl(pathname, search = '') {
    if (DRAWER_PATHS.some((re) => re.test(pathname))) return true;
    return /\/nuovo$/.test(pathname) && search.length > 1 && !NEW_AS_PAGE.some((re) => re.test(pathname));
}

function onKeydown(e) {
    if (e.key === 'Escape' && current && !document.querySelector('.modal-overlay')) {
        closeDrawer();
    }
}

export function closeDrawer({ reload = false } = {}) {
    if (!current) return;
    const { overlay, opener } = current;
    current = null;
    overlay.classList.remove('is-open');
    document.removeEventListener('keydown', onKeydown);
    document.documentElement.classList.remove('has-drawer');
    setTimeout(() => overlay.remove(), 200);
    if (opener && opener.isConnected) opener.focus();
    if (reload) reloadCurrentScreen();
}

function titleOf(components) {
    const form = components.find((c) => c.type === 'form');
    return (form && form.title) || 'Modifica';
}

function fill(body, titleEl, components) {
    body.replaceChildren();
    titleEl.textContent = titleOf(components);
    // La guida del form va nella testata del pannello (il titolo del form li' non si ripete).
    const form = components.find((c) => c.type === 'form');
    const g = guideParts(form && form.guide, titleOf(components));
    const slot = titleEl.parentNode && titleEl.parentNode.querySelector('.drawer__guide');
    if (slot) slot.replaceChildren(...(g.button ? [g.button] : []));
    // Dentro il pannello il form e' sempre aperto (non "chiuso sotto una scheda").
    const shown = components.map((c) => (c.type === 'form' ? { ...c, collapsed: false } : c));
    mountComponents(body, shown, 0);
    setTimeout(() => {
        const first = body.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
        if (first) first.focus({ preventScroll: true });
    }, 60);
}

export async function openDrawer(href, opener = null) {
    let data;
    try {
        const res = await fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!(res.headers.get('content-type') || '').includes('json')) throw new Error('not json');
        data = await res.json();
    } catch (e) {
        window.location.assign(href);
        return;
    }
    const components = data.components || [];
    if (!components.length) {
        window.location.assign(href);
        return;
    }

    if (current) {
        fill(current.body, current.titleEl, components);
        current.href = href;
        return;
    }

    const titleEl = el('h2', { className: 'drawer__title', id: 'drawer-title' }, ['']);
    const close = el('button', { type: 'button', className: 'drawer__close', 'aria-label': 'Chiudi' }, [icon('close')]);
    const body = el('div', { className: 'drawer__body' });
    const panel = el('aside', { className: 'drawer', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'drawer-title' }, [
        el('div', { className: 'drawer__head' }, [titleEl, el('span', { className: 'drawer__guide' }), close]),
        body,
    ]);
    const overlay = el('div', { className: 'drawer-overlay' }, [panel]);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) closeDrawer(); });
    close.addEventListener('click', () => closeDrawer());

    // I link dentro il pannello: un altro figlio resta nel pannello, il
    // resto chiude il pannello e apre la pagina come sempre.
    panel.addEventListener('click', (e) => {
        const link = e.target.closest('a[href]');
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        if ((link.target && link.target !== '_self') || link.hasAttribute('download')) return;
        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        e.preventDefault();
        if (isDrawerUrl(url.pathname, url.search)) {
            openDrawer(url.pathname + url.search);
            return;
        }
        closeDrawer();
        openInPanel(url.pathname + url.search);
    });

    document.body.appendChild(overlay);
    document.documentElement.classList.add('has-drawer');
    document.addEventListener('keydown', onKeydown);
    current = { overlay, body, titleEl, href, opener };
    fill(body, titleEl, components);
    requestAnimationFrame(() => overlay.classList.add('is-open'));
}

/**
 * Un'azione fatta dentro il pannello (es. "Confermato" su un appuntamento):
 * il pannello si ridisegna con lo stato nuovo, la pagina sotto si aggiorna.
 */
export async function refreshDrawer() {
    if (!current) return false;
    const { href } = current;
    try {
        const res = await fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await res.json();
        if (current && data.components && data.components.length) {
            fill(current.body, current.titleEl, data.components);
        }
    } catch (e) {
        closeDrawer();
    }
    reloadCurrentScreen();
    return true;
}

/** Il form e' dentro il pannello? (vedi form.js: dopo il salvataggio decide il pannello) */
export function inDrawer(node) {
    return Boolean(node && node.closest && node.closest('.drawer'));
}

function hasErrors(components) {
    return components.some((c) => c.type === 'form' && (c.messageType === 'error'
        || (c.sections || []).some((s) => (s.boxes || []).some((b) => (b.fields || []).some((f) => f.error)))));
}

/**
 * La risposta di un form salvato dentro il pannello: con errori si
 * ridisegna li' (il campo da correggere resta in vista); altrimenti il
 * pannello si chiude, l'esito compare in basso e la pagina sotto si aggiorna.
 */
export function handleDrawerResponse(responseData) {
    const components = (responseData && responseData.components) || [];
    if (!current) return;
    if (!components.length && responseData && responseData.ok === false) {
        toast(responseData.message || 'Non salvato.', 'error');
        return;
    }
    if (hasErrors(components)) {
        fill(current.body, current.titleEl, components.filter((c) => c.type === 'form'));
        return;
    }
    const notice = components.find((c) => c.type === 'notice');
    toast((notice && notice.text) || (responseData && responseData.message) || 'Salvato.', notice && notice.tone === 'error' ? 'error' : 'success');
    closeDrawer({ reload: true });
}
