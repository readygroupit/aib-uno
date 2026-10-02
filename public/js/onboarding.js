import { el } from './dom.js';
import { icon } from './icons.js';
import { registerComponent } from './components/registry.js';
import { mountSetup } from './setup.js';
import { buildFunctionsView, fetchFunctions } from './wizard.js';

/**
 * La Guida: unico punto di aiuto dell'app (prima erano due, il tour di
 * benvenuto e il wizard "cosa posso fare?", che si sovrapponevano).
 * Schede: panoramica, percorso di una pratica, dove trovare le cose,
 * "Cosa posso fare" (le funzioni, costruite dal server in base ai
 * permessi - vedi wizard.js) e "Cosa manca ancora" (la checklist di
 * configurazione, finche' ci sono passaggi obbligatori).
 *
 * Si apre da sola sulla home al primo accesso (finche' non e' chiusa con
 * "Ho capito, non mostrarmelo piu'", vedi AuthService::dismissOnboarding())
 * e finche' resta qualcosa di obbligatorio da configurare; in ogni altro
 * momento la si apre dal riquadro rosso nella toolbar del prompt (solo se
 * manca qualcosa) o dalla voce "Guida" del menu profilo.
 *
 * Testo editoriale scritto qui (spiega il sistema, non dati): un giro al
 * server per un contenuto fisso sarebbe solo rete in piu'.
 */
let overlayEl = null;

function onKeydown(e) {
    if (e.key === 'Escape') {
        closeGuide();
    }
}

export function closeGuide() {
    if (!overlayEl) return;
    overlayEl.remove();
    overlayEl = null;
    document.removeEventListener('keydown', onKeydown);
}

function dismissForever() {
    fetch('/onboarding/dismiss', {
        method: 'POST',
        headers: { 'X-CSRF-Token': window.UNO_CSRF || '' },
    }).catch(() => {});
    closeGuide();
}

function paragraph(text) {
    return el('p', { className: 'onboarding__text' }, [text]);
}

function pointList(items) {
    return el('div', { className: 'onboarding__list' }, items.map((item) =>
        el('div', { className: 'onboarding__point' }, [
            el('span', { className: 'onboarding__point-label' }, [item.label]),
            el('span', { className: 'onboarding__point-desc' }, [item.desc]),
        ])
    ));
}

// Etapes colorate (dot): riusano le classi .claim-table__stage-dot--* cosi'
// i colori sono gli stessi delle etichette vere nelle liste.
function steps(items) {
    return el('div', { className: 'onboarding__journey' }, items.map(([variant, label, desc]) =>
        el('div', { className: 'onboarding__journey-step' }, [
            el('span', { className: 'onboarding__journey-dot claim-table__stage-dot--' + variant }),
            el('div', {}, [
                el('span', { className: 'onboarding__journey-label' }, [label]),
                el('p', { className: 'onboarding__journey-desc' }, [desc]),
            ]),
        ])
    ));
}

// Un blocco della Guida (config/guide.php): {text}, {points: [[etichetta, descrizione]]}
// o {steps: [[colore, etichetta, descrizione]]}.
function renderBlocks(blocks) {
    return (blocks || []).map((block) => {
        if (block.points) return pointList(block.points.map(([label, desc]) => ({ label, desc })));
        if (block.steps) return steps(block.steps);
        return paragraph(block.text || '');
    });
}

const GUIDE = window.UNO_GUIDE || {};

function missingReadOnly() {
    return renderBlocks(GUIDE.missingReadOnly || [{ text: 'Chiedi a un amministratore lo stato della configurazione.' }]);
}

// Le schede di contenuto vengono dalla Guida del progetto; "Cosa posso
// fare" e "Cosa manca ancora" sono le stesse ovunque.
const TABS = [
    ...(GUIDE.tabs || []).map((tab) => ({ key: tab.key, label: tab.label, render: () => renderBlocks(tab.blocks) })),
    { key: 'functions', label: 'Cosa posso fare' },
    { key: 'missing', label: 'Cosa manca ancora' },
];

export function setupIsPending(setup) {
    return !!setup && setup.progress.done < setup.progress.total;
}

// Stato della checklist condiviso fra la Guida e il riquadro rosso nella
// toolbar (hero.js): una sola fonte, un solo fetch per pagina.
let setupState = null;
const setupListeners = new Set();

function publishSetup(state) {
    setupState = state;
    setupListeners.forEach((callback) => callback(state));
}

export function getSetupState() {
    return setupState;
}

/** Chiama subito (se lo stato e' gia' noto) e a ogni cambiamento. */
export function onSetupChange(callback) {
    setupListeners.add(callback);
    if (setupState !== null) callback(setupState);
}

// null per chi non puo' configurare (403) o se la richiesta fallisce: la
// Guida funziona anche senza.
export async function loadSetupState() {
    if (!window.UNO_CAN_CONFIGURE) return null;
    try {
        const response = await fetch('/configurazione/stato', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const state = response.ok ? await response.json() : null;
        publishSetup(state);
        return state;
    } catch {
        return null;
    }
}

/**
 * @param {{tab?: string, entity?: ?string, functionsData?: ?object}} [options]
 *   tab: scheda iniziale. entity: entita' in vista, per "Campi da
 *   conoscere". functionsData: dati gia' pronti per la scheda funzioni
 *   (dal prompt, vedi il renderer 'wizard' in fondo) invece di un fetch.
 */
export function openGuide({ tab = null, entity = null, functionsData = null } = {}) {
    closeGuide();

    // Chi non ha il permesso di configurare resta con state null: vede la
    // scheda "Cosa manca ancora" come sola lettura e, se e' il primo
    // accesso, puo' spegnere il tour.
    const canConfigure = !!window.UNO_CAN_CONFIGURE;
    const firstTime = !!window.UNO_SHOW_ONBOARDING;
    const body = el('div', { className: 'onboarding__body' });
    const tabButtons = new Map();
    const footer = el('div', { className: 'onboarding__footer' });
    let currentKey = null;

    const pending = () => setupIsPending(setupState);
    // Con lo stato noto e tutto completo la scheda non serve piu'.
    // Esiste solo dove c'e' qualcosa da configurare (pacchetto connettori) o
    // la Guida ne descrive lo stato a parole.
    const hasSetup = canConfigure || !!GUIDE.missingReadOnly;
    const showMissing = () => hasSetup && (!canConfigure || setupState === null || pending() || currentKey === 'missing');
    const visibleTabs = () => TABS.filter((t) => t.key !== 'missing' || showMissing());

    function renderMissing() {
        const holder = el('div', { className: 'setup' });
        if (setupState) {
            mountSetup(holder, setupState, { onChange: (next) => { publishSetup(next); renderFooter(); markTabs(); } });
        } else {
            missingReadOnly().forEach((node) => holder.appendChild(node));
        }
        return [holder];
    }

    function renderFunctions() {
        const holder = el('div');
        holder.appendChild(el('p', { className: 'onboarding__text' }, ['Caricamento...']));
        const ready = functionsData ? Promise.resolve(functionsData) : fetchFunctions(entity);
        ready.then((data) => {
            holder.textContent = '';
            holder.appendChild(data ? buildFunctionsView(data) : paragraph('Non riesco a caricare l\'elenco delle funzioni. Riprova tra poco.'));
        });
        return [holder];
    }

    function markTabs() {
        const alert = tabButtons.get('missing')?.querySelector('.onboarding__tab-alert');
        if (alert) alert.hidden = !pending();
    }

    function renderFooter() {
        footer.textContent = '';
        // "Non mostrarmelo piu'" solo se ha ancora senso: mai con cose
        // obbligatorie da fare, e solo finche' non e' stato gia' spento.
        if (firstTime && !pending()) {
            footer.appendChild(el('button', { type: 'button', className: 'btn btn--primary', onClick: dismissForever }, ['Ho capito, non mostrarmelo più']));
        } else {
            footer.appendChild(el('button', { type: 'button', className: 'btn btn--secondary', onClick: closeGuide }, ['Chiudi']));
        }
    }

    function selectTab(key) {
        const found = TABS.find((t) => t.key === key) || TABS[0];
        currentKey = found.key;
        tabButtons.forEach((b, k) => b.classList.toggle('is-active', k === found.key));
        body.textContent = '';
        const nodes = found.key === 'missing' ? renderMissing() : found.key === 'functions' ? renderFunctions() : found.render();
        nodes.forEach((node) => body.appendChild(node));
    }

    const tabBar = el('div', { className: 'onboarding__tabs' });
    visibleTabs().forEach((t) => {
        const children = [t.label];
        if (t.key === 'missing') {
            const alert = icon('circle-exclamation', 'onboarding__tab-alert');
            alert.title = 'Ci sono passaggi da completare';
            alert.hidden = setupState !== null && !pending();
            children.push(alert);
        }
        const button = el('button', { type: 'button', className: 'onboarding__tab', onClick: () => selectTab(t.key) }, children);
        tabButtons.set(t.key, button);
        tabBar.appendChild(button);
    });

    const panel = el('div', { className: 'onboarding__panel' }, [
        el('div', { className: 'onboarding__header' }, [
            el('img', { src: window.UNO_LOGO_SQUARE || window.UNO_LOGO, alt: '', className: 'onboarding__logo' }),
            el('div', { className: 'onboarding__header-text' }, [
                el('h2', { className: 'onboarding__title' }, [firstTime ? (GUIDE.welcome || `Benvenuto in ${window.UNO_APP_NAME || 'Uno'}`) : 'Guida']),
                el('p', { className: 'onboarding__subtitle' }, [pending()
                    ? 'Ci sono ancora passaggi da completare per essere operativi.'
                    : (GUIDE.subtitle || 'Come funziona il sistema e cosa puoi fare.')]),
            ]),
            el('button', { type: 'button', className: 'onboarding__close', title: 'Chiudi', onClick: closeGuide }, ['×']),
        ]),
        tabBar,
        body,
        footer,
    ]);

    overlayEl = el(
        'div',
        {
            className: 'wizard-overlay',
            onClick: (e) => {
                if (e.target === overlayEl) closeGuide();
            },
        },
        [panel]
    );

    document.body.appendChild(overlayEl);
    document.addEventListener('keydown', onKeydown);
    renderFooter();
    selectTab(tabButtons.has(tab) ? tab : TABS[0].key);
}

// "Cosa puoi fare" / "aiuto" dal prompt: la Guida diventa contenuto della
// pagina (il prompt si sposta a sinistra come per ogni altro risultato e
// risponde in chat), non un modale sopra. Se config/guide.php ha una
// 'landing', la Guida e' una presentazione da landing page: titoli grandi,
// frasi corte, una idea per sezione, sezioni alternate. Altrimenti le
// schede della Guida, una card ciascuna.
const ACCENTS = ['brass', 'teal', 'lilac', 'moss'];

function actionButtons(actions, extraClass = '') {
    return el('div', { className: 'landing__actions ' + extraClass }, (actions || []).map((action) =>
        el('button', {
            type: 'button',
            className: 'landing__btn' + (action.primary ? ' landing__btn--primary' : ''),
            onClick: () => window.unoSubmitPrompt && window.unoSubmitPrompt(action.prompt),
        }, [action.label])
    ));
}

function heroBand(hero) {
    return el('section', { className: 'landing__hero' }, [
        el('div', { className: 'landing__hero-copy' }, [
            el('span', { className: 'landing__eyebrow landing__eyebrow--light' }, [hero.eyebrow || '']),
            el('h2', { className: 'landing__hero-title' }, [hero.title || '']),
            el('div', { className: 'landing__hero-lines' }, (hero.lines || []).map((line, i) =>
                el('span', { className: 'landing__hero-line landing__hero-line--' + ACCENTS[i % ACCENTS.length] }, [line]))),
            actionButtons(hero.actions, 'landing__actions--hero'),
        ]),
        el('div', { className: 'landing__hero-art' }, [
            el('span', { className: 'landing__hero-glow' }),
            el('img', { className: 'landing__hero-mascot', src: window.UNO_LOGO_SQUARE || window.UNO_LOGO, alt: '' }),
        ]),
    ]);
}

function figuresBand(figures) {
    return el('section', { className: 'landing__figures' }, figures.map((figure, i) =>
        el('div', { className: 'landing__figure landing__figure--' + ACCENTS[i % ACCENTS.length] }, [
            el('span', { className: 'landing__figure-value' }, [figure.value]),
            el('span', { className: 'landing__figure-label' }, [figure.label]),
        ])));
}

function featureVisual(visual) {
    if (visual.chat) {
        return el('div', { className: 'landing__chat' }, visual.chat.map(([who, text]) =>
            el('div', { className: 'landing__bubble landing__bubble--' + who }, [
                who === 'uno' ? el('img', { className: 'landing__bubble-avatar', src: window.UNO_LOGO_SQUARE || window.UNO_LOGO, alt: '' }) : null,
                el('span', {}, [text]),
            ])));
    }
    if (visual.chips) {
        // Un assaggio, non l'elenco completo: una landing non e' un catalogo.
        const shown = visual.chips.slice(0, 9);
        const chips = shown.map((label, i) =>
            el('span', { className: 'landing__chip landing__chip--' + ACCENTS[i % ACCENTS.length] }, [label]));
        if (visual.chips.length > shown.length) {
            chips.push(el('span', { className: 'landing__chip landing__chip--more' }, [`+${visual.chips.length - shown.length} altri`]));
        }
        return el('div', { className: 'landing__chips' }, chips);
    }
    if (visual.url) {
        return el('div', { className: 'landing__browser' }, [
            el('div', { className: 'landing__browser-bar' }, [
                el('span', { className: 'landing__browser-dots' }, [el('i'), el('i'), el('i')]),
                el('span', { className: 'landing__browser-url' }, [visual.url]),
            ]),
            el('div', { className: 'landing__browser-body' }, [
                el('span', { className: 'landing__browser-check' }, [icon('circle-check')]),
                el('span', { className: 'landing__browser-text' }, ['Online']),
            ]),
        ]);
    }
    return null;
}

function featureRow(feature, index) {
    return el('section', { className: 'landing__feature' + (index % 2 ? ' landing__feature--reverse' : '') }, [
        el('div', { className: 'landing__feature-copy' }, [
            el('span', { className: 'landing__eyebrow landing__eyebrow--' + ACCENTS[index % ACCENTS.length] }, [feature.eyebrow || '']),
            el('h3', { className: 'landing__feature-title' }, [feature.title]),
            el('p', { className: 'landing__feature-text' }, [feature.text]),
        ]),
        el('div', { className: 'landing__feature-visual' }, [featureVisual(feature.visual || {})]),
    ]);
}

function stepsBand(steps) {
    return el('section', { className: 'landing__steps' }, [
        el('h3', { className: 'landing__section-title' }, [steps.title]),
        el('ol', { className: 'landing__steps-list' }, steps.items.map(([label, desc], i) =>
            el('li', { className: 'landing__step landing__step--' + ACCENTS[i % ACCENTS.length] }, [
                el('span', { className: 'landing__step-num' }, [String(i + 1)]),
                el('span', { className: 'landing__step-label' }, [label]),
                el('span', { className: 'landing__step-desc' }, [desc]),
            ]))),
    ]);
}

function ctaBand(cta) {
    return el('section', { className: 'landing__cta' }, [
        el('h3', { className: 'landing__cta-title' }, [cta.title]),
        actionButtons(cta.actions, 'landing__actions--center'),
    ]);
}

function landing(guide, data) {
    const l = guide.landing;
    return el('div', { className: 'landing' }, [
        l.hero ? heroBand(l.hero) : null,
        (l.figures || []).length ? figuresBand(l.figures) : null,
        ...(l.features || []).map(featureRow),
        l.steps ? stepsBand(l.steps) : null,
        l.cta ? ctaBand(l.cta) : null,
        el('section', { className: 'card guide-page__card' }, [
            el('h3', { className: 'landing__section-title landing__section-title--left' }, ['Tutto quello che puoi chiedermi']),
            buildFunctionsView(data),
        ]),
    ]);
}

registerComponent('wizard', (data) => {
    const guide = data.guide || {};
    if (guide.landing) {
        return landing(guide, data);
    }

    const sections = (guide.tabs || []).map((tab) => el('section', { className: 'card guide-page__card' }, [
        el('h2', { className: 'guide-page__title' }, [tab.label]),
        ...renderBlocks(tab.blocks),
    ]));
    sections.push(el('section', { className: 'card guide-page__card' }, [
        el('h2', { className: 'guide-page__title' }, ['Cosa posso fare']),
        buildFunctionsView(data),
    ]));

    return el('div', { className: 'guide-page' }, sections);
});
