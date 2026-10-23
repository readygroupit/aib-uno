import { el } from './dom.js';
import { icon } from './icons.js';

/**
 * L'avviso discreto del registro: quando e' il momento (pausa, fine
 * giornata) compare in basso "Cosa hai fatto stamattina?". Non blocca
 * niente; "Piu' tardi" lo nasconde per un'ora su questo browser.
 */
const SNOOZE_KEY = 'uno.worklog.snoozeUntil';

function snoozed() {
    try {
        return Number(window.localStorage.getItem(SNOOZE_KEY) || 0) > Date.now();
    } catch (e) {
        return false;
    }
}

function snooze() {
    try {
        window.localStorage.setItem(SNOOZE_KEY, String(Date.now() + 60 * 60 * 1000));
    } catch (e) {
        // senza memoria del browser ricompare al prossimo caricamento: va bene lo stesso
    }
}

// Il check-in si apre nel pannello dei risultati, senza ricaricare la
// pagina: un link temporaneo li' dentro lo intercetta hero.js.
function openInResults(e, node) {
    node.remove();
    const panel = document.querySelector('.results-panel');
    if (!panel) return;
    e.preventDefault();
    const link = el('a', { href: '/registro/check-in', hidden: '' });
    panel.appendChild(link);
    link.click();
    link.remove();
}

export async function checkWorklogNudge() {
    if (!window.UNO_WORKLOG || snoozed() || window.location.pathname.startsWith('/registro/check-in')) return;
    let data;
    try {
        const response = await fetch('/registro/stato', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        data = await response.json();
    } catch (e) {
        return;
    }
    if (!data || !data.due) return;

    document.querySelector('.wl-nudge')?.remove();
    const node = el('div', { className: 'wl-nudge', role: 'status' }, [
        el('span', { className: 'wl-nudge__icon' }, [icon('clock')]),
        el('div', { className: 'wl-nudge__text' }, [
            el('strong', {}, [data.due.question]),
            el('span', {}, ['Due minuti per segnarlo: conferma e correggi i tempi.']),
        ]),
        el('a', { className: 'btn btn--primary btn--small', href: '/registro/check-in', onClick: (e) => openInResults(e, node) }, ['Segna']),
        el('button', { type: 'button', className: 'btn btn--ghost btn--small', onClick: () => { snooze(); node.remove(); } }, ['Piu\' tardi']),
    ]);
    document.body.appendChild(node);
    requestAnimationFrame(() => node.classList.add('is-visible'));
}
