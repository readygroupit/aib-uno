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

/**
 * La conferma del gestionale, al posto di window.confirm: un modale con
 * "Annulla" e il pulsante dell'azione (rosso se toglie o elimina).
 * Esc, la x o il clic fuori valgono "Annulla".
 *
 * @param {{title?: string, text: string, confirmLabel?: string, danger?: boolean}} options
 * @returns {Promise<boolean>}
 */
export function confirmModal({ title = 'Confermi?', text, confirmLabel = 'Conferma', danger = null }) {
    const isDanger = danger ?? /\b(togli|elimin|cancell|spegn|rimuov)/i.test(`${text} ${confirmLabel}`);
    return new Promise((resolve) => {
        let done = false;
        const finish = (value) => {
            if (done) return;
            done = true;
            document.removeEventListener('keydown', onKeydown);
            closeModal();
            resolve(value);
        };
        const cancel = el('button', { type: 'button', className: 'btn btn--secondary', onClick: () => finish(false) }, ['Annulla']);
        const ok = el('button', { type: 'button', className: `btn ${isDanger ? 'btn--delete' : 'btn--primary'}`, onClick: () => finish(true) }, [confirmLabel]);
        openModal({
            title,
            tone: isDanger ? 'error' : 'info',
            body: [
                el('p', { className: 'modal__text' }, [text]),
                el('div', { className: 'modal__actions' }, [cancel, ok]),
            ],
        });
        // Chiuso dalla x, da Esc o dal clic fuori: vale "Annulla".
        const watch = setInterval(() => {
            if (!overlayEl) {
                clearInterval(watch);
                finish(false);
            } else if (done) {
                clearInterval(watch);
            }
        }, 150);
        setTimeout(() => ok.focus(), 30);
    });
}

/** Un testo da copiare a mano (quando gli appunti del browser non si possono usare). */
export function copyModal({ title = 'Copia da qui', text }) {
    const area = el('textarea', { className: 'field__input field__input--textarea modal__copy', rows: '3', readonly: '' });
    area.value = text;
    openModal({ title, tone: 'info', body: [area] });
    setTimeout(() => { area.focus(); area.select(); }, 30);
}
