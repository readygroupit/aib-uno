/**
 * "Sto elaborando": quando una richiesta al server (una pagina aperta nel
 * pannello, un salvataggio, una domanda in chat, un collegamento) dura piu'
 * di un attimo, al centro dello schermo compare un riquadro con la rotella.
 *
 * Un solo punto per tutto il sito: avvolge window.fetch, cosi' ogni
 * componente lo ha senza doverlo chiamare. Si vede solo dopo SHOW_AFTER ms
 * (le risposte veloci non lampeggiano) e non blocca la pagina (si puo'
 * continuare a scorrere). Le richieste di sottofondo (suggerimenti mentre
 * si scrive, stato della Configurazione, avvisi periodici) non lo mostrano.
 */
const SHOW_AFTER = 250;
const BACKGROUND = [
    /\/cerca(\?|$)/,
    /^\/configurazione\/stato/,
    /^\/registro\/stato/,
    /^\/onboarding\//,
    /^\/agenti\/messaggi$/,
];

let pending = 0;
let timer = null;
let node = null;

function build() {
    const box = document.createElement('div');
    box.className = 'page-loader';
    box.setAttribute('role', 'status');
    box.setAttribute('aria-live', 'polite');
    const card = document.createElement('div');
    card.className = 'page-loader__card';
    const spinner = document.createElement('span');
    spinner.className = 'page-loader__spinner';
    spinner.setAttribute('aria-hidden', 'true');
    const text = document.createElement('span');
    text.className = 'page-loader__text';
    text.textContent = 'Sto elaborando...';
    card.append(spinner, text);
    box.appendChild(card);
    return box;
}

function start() {
    pending++;
    if (pending === 1 && timer === null) {
        timer = setTimeout(() => {
            timer = null;
            node ||= build();
            document.body.appendChild(node);
            requestAnimationFrame(() => node && node.classList.add('is-visible'));
        }, SHOW_AFTER);
    }
}

function stop() {
    pending = Math.max(0, pending - 1);
    if (pending > 0) return;
    clearTimeout(timer);
    timer = null;
    if (node) {
        node.classList.remove('is-visible');
        node.remove();
    }
}

function isBackground(input) {
    try {
        const url = new URL(typeof input === 'string' ? input : input.url, window.location.href);
        return url.origin !== window.location.origin || BACKGROUND.some((re) => re.test(url.pathname + url.search));
    } catch {
        return true;
    }
}

const nativeFetch = window.fetch.bind(window);
window.fetch = (input, init) => {
    if (isBackground(input)) return nativeFetch(input, init);
    start();
    return nativeFetch(input, init).finally(stop);
};

// Tornando indietro alla pagina dalla cache del browser, niente rotella rimasta.
window.addEventListener('pageshow', () => {
    pending = 0;
    stop();
});
