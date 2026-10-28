import { el } from './dom.js';
import { icon } from './icons.js';
import { openInPanel } from './components/hero.js';

/**
 * Il calendarietto accanto alle frecce (settimana prima/dopo, Oggi): si
 * sceglie un giorno qualsiasi e si apre il suo periodo, senza cliccare
 * la freccia indietro dieci volte. hrefFor(AAAA-MM-GG) da' l'indirizzo
 * (il server trova da solo il lunedi' della settimana).
 */
export function dateJump(hrefFor, { title = 'Vai a una data', value = '' } = {}) {
    const input = el('input', { type: 'date', className: 'cal-nav__jump-input', value, 'aria-label': title });
    input.addEventListener('click', () => {
        try {
            input.showPicker();
        } catch (e) {
            // Browser senza showPicker: il campo trasparente sopra il pulsante apre comunque il suo calendario.
        }
    });
    input.addEventListener('change', () => {
        if (input.value) openInPanel(hrefFor(input.value));
    });

    return el('label', { className: 'cal-nav__btn cal-nav__jump', title }, [icon('calendar'), input]);
}
