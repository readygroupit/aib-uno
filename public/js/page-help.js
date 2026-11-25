import { el } from './dom.js';
import { icon } from './icons.js';
import { openGuideModal } from './guide.js';

/**
 * "Come funziona questa pagina": un pulsante tondo, sempre nello stesso
 * posto (in basso a destra) su ogni pagina. Apre nel modale la guida della
 * pagina in cui si e', che il server sceglie dall'indirizzo
 * (/guida-pagina, vedi App\\Help\\PageGuideService e config/page-guides.php).
 */
let button = null;

export function ensurePageHelp() {
    if (button && button.isConnected) return;
    button = el('button', {
        type: 'button',
        className: 'page-help',
        title: 'Come funziona questa pagina',
        'aria-label': 'Come funziona questa pagina',
    }, [icon('circle-question'), el('span', { className: 'page-help__label' }, ['Come funziona'])]);
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const res = await fetch(`/guida-pagina?path=${encodeURIComponent(window.location.pathname)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            openGuideModal(data.guide || {}, 'questa pagina');
        } catch (e) {
            openGuideModal({ title: 'Come funziona', text: 'Guida non disponibile: riprova tra poco.' });
        } finally {
            button.disabled = false;
        }
    });
    document.body.appendChild(button);
}
