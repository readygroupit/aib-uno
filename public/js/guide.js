import { el } from './dom.js';
import { icon } from './icons.js';
import { openModal } from './modal.js';

/**
 * La mini guida di una pagina, una scheda, una sezione o un riquadro:
 * un'icona "i" accanto al titolo apre in un modale una spiegazione breve,
 * cosi' chi usa il gestionale non resta bloccato. Vale per tutti i
 * componenti: il server manda 'guide' insieme ai dati del componente.
 *
 * guide: una frase, oppure { title?, text?, steps?: string[], tip? }.
 * Il testo arriva dal server: si scrive sempre come testo (el), mai come HTML.
 *
 * Uso: const g = guideParts(data.guide); mettere g.button accanto al titolo.
 * Con guide vuota il pulsante e' null (niente da mostrare).
 */
export function guideParts(guide, label = '') {
    if (!guide) {
        return { button: null, panel: null };
    }
    const data = typeof guide === 'string' ? { text: guide } : guide;

    const button = el('button', {
        type: 'button',
        className: 'guide-button',
        'aria-haspopup': 'dialog',
        'aria-label': label ? `Guida: ${label}` : 'Apri la guida',
        title: 'Come funziona',
    }, [icon('circle-info')]);

    button.addEventListener('click', (e) => {
        // Il titolo puo' essere a sua volta un pulsante (parte che si apre
        // e chiude): la "i" apre solo la guida.
        e.preventDefault();
        e.stopPropagation();
        openGuideModal(data, label);
    });

    // panel resta per compatibilita': la guida ora si apre in un modale,
    // non occupa spazio nella pagina.
    return { button, panel: null };
}

/** Apre una guida nel modale (anche "Come funziona questa pagina", vedi page-help.js). */
export function openGuideModal(guide, label = '') {
    const data = typeof guide === 'string' ? { text: guide } : (guide || {});
    openModal({
        title: data.title || (label ? `Come funziona: ${label}` : 'Come funziona'),
        tone: 'guide',
        body: [
            data.text ? el('p', { className: 'guide__text' }, [data.text]) : null,
            data.steps && data.steps.length
                ? el('ol', { className: 'guide__steps' }, data.steps.map((s) => el('li', {}, [s])))
                : null,
            data.tip ? el('p', { className: 'guide__tip' }, [icon('lightbulb'), el('span', {}, [data.tip])]) : null,
        ].filter(Boolean),
    });
}
