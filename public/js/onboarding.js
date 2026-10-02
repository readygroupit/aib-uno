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

// Stessi 5 stati/colori di ListCustomersTool::STAGE_META - non un elenco
// scritto a parte che puo' disallinearsi da quello vero.
function journeySteps() {
    const steps = [
        ['accent', 'Raccolta documenti', 'Si chiedono al cliente i documenti che servono: carta d\'imbarco, documento d\'identita\', mandato.'],
        ['strong', 'Reclamo inviato', 'La richiesta e\' partita verso il vettore aereo. Si attende una risposta.'],
        ['info', 'In conciliazione', 'Se il vettore non risponde o rifiuta, la pratica passa a ConciliaWeb per una decisione terza.'],
        ['warning', 'Rimborsato', 'Il cliente ha ricevuto quanto dovuto. La pratica e\' chiusa con esito positivo.'],
        ['danger', 'Respinta', 'La richiesta non ha avuto esito positivo, in nessuna delle fasi precedenti.'],
    ];

    return el('div', { className: 'onboarding__journey' }, steps.map(([variant, label, desc]) =>
        el('div', { className: 'onboarding__journey-step' }, [
            el('span', { className: 'onboarding__journey-dot claim-table__stage-dot--' + variant }),
            el('div', {}, [
                el('span', { className: 'onboarding__journey-label' }, [label]),
                el('p', { className: 'onboarding__journey-desc' }, [desc]),
            ]),
        ])
    ));
}

function missingReadOnly() {
    return [
        paragraph('Questo e\' un ambiente dimostrativo: quello che vedi funziona davvero sui dati veri, ma alcuni collegamenti esterni non sono ancora attivi. Chiedi a un amministratore di completarli.'),
        pointList([
            { label: 'Jotform', desc: 'i moduli di raccolta lead non sono collegati - i contatti vanno inseriti a mano.' },
            { label: 'Google Sheets', desc: 'se usato come archivio esistente, non c\'e\' ancora un collegamento automatico.' },
            { label: 'ConciliaWeb', desc: 'invio e monitoraggio delle pratiche in conciliazione non sono automatizzati - lo stato va aggiornato a mano.' },
            { label: 'WhatsApp', desc: 'le comunicazioni restano registrate nel sistema, ma non partono davvero.' },
            { label: 'Modelli di messaggio', desc: 'testi predefiniti per solleciti e comunicazioni ricorrenti.' },
            { label: 'Classificazione del disservizio', desc: 'va scelta a mano per ogni pratica, non ancora riconosciuta in automatico.' },
        ]),
    ];
}

const TABS = [
    {
        key: 'overview',
        label: 'Panoramica',
        render: () => [
            paragraph('Assilevi segue i reclami dei passeggeri aerei - ritardi, cancellazioni, negato imbarco, bagagli - dal primo contatto fino al rimborso.'),
            paragraph('Il prompt in cima alla pagina e\' un assistente vero: scrivi cosa vuoi fare (es. "elenco clienti") ed esegue l\'operazione al posto tuo, senza dover cercare la voce di menu giusta.'),
            pointList([
                { label: 'Contatti', desc: 'un lead grezzo, prima ancora di diventare cliente.' },
                { label: 'Clienti', desc: 'la situazione della pratica di ognuno, in un colpo d\'occhio.' },
                { label: 'Pratiche', desc: 'il fascicolo completo: documenti, comunicazioni, rimborso.' },
            ]),
        ],
    },
    {
        key: 'journey',
        label: 'Percorso pratica',
        render: () => [
            paragraph('Ogni pratica passa (nell\'ordine) per questi 5 stati - sono gli stessi che vedi come etichette colorate in Clienti e Pratiche.'),
            journeySteps(),
        ],
    },
    {
        key: 'where',
        label: 'Dove trovare',
        render: () => [
            pointList([
                { label: 'Clienti', desc: 'chi ha documenti mancanti, chi e\' in conciliazione, importi in gioco.' },
                { label: 'Pratiche / Contatti', desc: 'gestione dettagliata di lead e pratiche legali.' },
                { label: 'Documenti richiesti', desc: 'cosa manca ancora per completare un fascicolo.' },
                { label: 'Comunicazioni', desc: 'il registro di cosa e\' stato scritto/inviato a un cliente.' },
                { label: 'Rimborsi', desc: 'importi richiesti, accettati, pagati.' },
                { label: 'Menu (icona a griglia in basso)', desc: 'tutte le funzioni disponibili, raggruppate per area.' },
            ]),
        ],
    },
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
    const showMissing = () => !canConfigure || setupState === null || pending() || currentKey === 'missing';
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
                el('h2', { className: 'onboarding__title' }, [firstTime ? 'Benvenuto in Assilevi' : 'Guida']),
                el('p', { className: 'onboarding__subtitle' }, [pending()
                    ? 'Ci sono ancora passaggi da completare per essere operativi.'
                    : 'Come funziona il sistema e cosa puoi fare.']),
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

// Il prompt risponde con un componente 'wizard' ("aiuto", "cosa posso
// fare"): invece di un secondo pannello di aiuto, apre la Guida sulla
// scheda delle funzioni con i dati gia' arrivati.
registerComponent('wizard', (data) => {
    openGuide({ tab: 'functions', functionsData: data });
    return null;
});
