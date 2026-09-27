/**
 * Piccolo helper per costruire DOM in sicurezza: il testo passa sempre da
 * textContent/createTextNode, mai innerHTML con dati che vengono dal
 * server (evita XSS su valori salvati in database).
 */
export function el(tag, props = {}, children = []) {
    const node = document.createElement(tag);

    for (const [key, value] of Object.entries(props)) {
        if (value == null) continue;
        if (key === 'className') {
            node.className = value;
        } else if (key.startsWith('on') && typeof value === 'function') {
            node.addEventListener(key.slice(2).toLowerCase(), value);
        } else {
            node.setAttribute(key, value);
        }
    }

    for (const child of children) {
        if (child == null) continue;
        node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    }

    return node;
}

/**
 * Dissolvenza in ingresso scaglionata (a cascata) - stessa idea di
 * mountComponents() in registry.js, ma come helper a se' cosi' anche un
 * componente con una lista interna di pezzi (es. le card del menu-grid)
 * puo' farli comparire uno alla volta invece che tutti insieme. I nodi
 * vanno gia' appesi al DOM da chi chiama (qui si tocca solo la classe
 * che pilota opacita'/spostamento, vedi .is-entering in base.css).
 */
export function staggerReveal(nodes, staggerMs = 130) {
    nodes.forEach((node, index) => {
        node.classList.add('is-entering');
        setTimeout(() => {
            requestAnimationFrame(() => node.classList.remove('is-entering'));
        }, index * staggerMs);
    });
}

/**
 * Indicatore "a pillola" che scorre dietro un gruppo di tab invece di
 * ricolorare da zero il bottone che diventa attivo ogni volta (quello
 * che succedeva prima - segnalato dall'utente: voleva un elemento
 * distaccato che si sposta, non un cambio di colore istantaneo in due
 * punti diversi). Un solo elemento assoluto la cui posizione/larghezza
 * si anima (transition sul CSS del chiamante) verso il bottone attivo -
 * l'aspetto (colore/ombra) resta al className passato qui, solo la
 * logica di misura/posizionamento e' condivisa fra menu e tabelle.
 * `container` deve avere position:relative nel CSS del chiamante.
 */
export function createSlidingIndicator(container, className) {
    const indicator = el('div', { className });
    container.insertBefore(indicator, container.firstChild);

    let firstMove = true;

    return function moveIndicatorTo(button) {
        if (!button) return;

        // Il primo posizionamento (al montaggio) deve essere istantaneo,
        // non scorrere da 0,0 in alto a sinistra verso il tab di default
        // - si anima solo da qui in poi, sui click veri.
        if (firstMove) {
            indicator.style.transition = 'none';
        }

        indicator.style.left = `${button.offsetLeft}px`;
        indicator.style.width = `${button.offsetWidth}px`;
        indicator.style.top = `${button.offsetTop}px`;
        indicator.style.height = `${button.offsetHeight}px`;

        if (firstMove) {
            void indicator.offsetHeight; // forza il reflow prima di riabilitare la transizione CSS
            indicator.style.transition = '';
            firstMove = false;
        }
    };
}
