import { el } from './dom.js';

/**
 * Modale generico per avvisi (errori di validazione, di comunicazione,
 * ecc.) - non il wizard (public/js/wizard.js), che ha il suo overlay
 * apposta piu' ricco (ricerca, logo, header colorato): qui serve solo
 * "avviso breve, un pulsante per chiudere", riusabile da qualunque
 * componente senza dover ricostruire overlay/ESC/click-fuori ogni volta.
 */
let overlayEl = null;

function onKeydown(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
}

export function closeModal() {
    if (!overlayEl) return;
    overlayEl.remove();
    overlayEl = null;
    document.removeEventListener('keydown', onKeydown);
}

/**
 * @param {{title: string, body: string|Node|Array<string|Node>, tone?: 'error'|'info'}} options
 */
export function openModal({ title, body, tone = 'error' }) {
    closeModal();

    const bodyChildren = Array.isArray(body) ? body : [body];

    const panel = el('div', { className: 'modal__panel modal__panel--' + tone }, [
        el('div', { className: 'modal__header' }, [
            el('h3', { className: 'modal__title' }, [title]),
            el('button', { type: 'button', className: 'modal__close', title: 'Chiudi', onClick: closeModal }, ['×']),
        ]),
        el('div', { className: 'modal__body' }, bodyChildren),
    ]);

    overlayEl = el(
        'div',
        {
            className: 'modal-overlay',
            onClick: (e) => {
                if (e.target === overlayEl) closeModal();
            },
        },
        [panel]
    );
    document.body.appendChild(overlayEl);
    document.addEventListener('keydown', onKeydown);
}
