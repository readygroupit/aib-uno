import { el } from './dom.js';

/**
 * Tour di benvenuto - si apre da solo finche' non viene chiuso con "Ho
 * capito, non mostrarmelo piu'" (vedi AuthService::dismissOnboarding(),
 * window.UNO_SHOW_ONBOARDING in layout.phtml). Diverso dal wizard "cosa
 * posso fare?" (public/js/wizard.js): quello e' un riferimento
 * consultabile a tema/campo, questo e' un giro guidato a schede, mostrato
 * una volta sola per introdurre il sistema - stesso linguaggio visivo
 * dell'overlay (vedi .wizard-overlay in wizard.css, riusato cosi' com'e'
 * per il fondo) ma un pannello suo con le schede al posto della ricerca.
 *
 * Contenuto scritto qui, non generato dal server: e' testo editoriale
 * (spiega il sistema, non dati), stesso principio dei testi statici gia'
 * visti altrove nel framework - un giro a /wizard per un contenuto fisso
 * sarebbe solo un giro di rete in piu' senza motivo.
 */
let overlayEl = null;

function onKeydown(e) {
    if (e.key === 'Escape') {
        closeOnboarding();
    }
}

export function closeOnboarding() {
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
    closeOnboarding();
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
        label: 'Il percorso di una pratica',
        render: () => [
            paragraph('Ogni pratica passa (nell\'ordine) per questi 5 stati - sono gli stessi che vedi come etichette colorate in Clienti e Pratiche.'),
            journeySteps(),
        ],
    },
    {
        key: 'where',
        label: 'Dove trovare le cose',
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
    {
        key: 'missing',
        label: 'Cosa manca ancora',
        render: () => [
            paragraph('Questo e\' un ambiente dimostrativo: quello che vedi funziona davvero sui dati veri, ma alcuni collegamenti esterni non sono ancora attivi.'),
            pointList([
                { label: 'Jotform', desc: 'i moduli di raccolta lead non sono collegati - i contatti vanno inseriti a mano.' },
                { label: 'Google Sheets', desc: 'se usato come archivio esistente, non c\'e\' ancora un collegamento automatico.' },
                { label: 'ConciliaWeb', desc: 'invio e monitoraggio delle pratiche in conciliazione non sono automatizzati - lo stato va aggiornato a mano.' },
                { label: 'WhatsApp', desc: 'le comunicazioni restano registrate nel sistema, ma non partono davvero.' },
                { label: 'Modelli di messaggio', desc: 'non esistono ancora testi predefiniti per solleciti e comunicazioni ricorrenti.' },
                { label: 'Classificazione del disservizio', desc: 'va scelta a mano per ogni pratica, non ancora riconosciuta in automatico.' },
            ]),
        ],
    },
];

export function openOnboarding() {
    closeOnboarding();

    const body = el('div', { className: 'onboarding__body' });
    const tabButtons = [];

    function selectTab(key) {
        const tab = TABS.find((t) => t.key === key) || TABS[0];
        tabButtons.forEach((b) => b.classList.toggle('is-active', b.dataset.tabKey === tab.key));
        body.textContent = '';
        tab.render().forEach((node) => body.appendChild(node));
    }

    const tabBar = el(
        'div',
        { className: 'onboarding__tabs' },
        TABS.map((tab) => {
            const button = el(
                'button',
                { type: 'button', className: 'onboarding__tab', onClick: () => selectTab(tab.key) },
                [tab.label]
            );
            button.dataset.tabKey = tab.key;
            tabButtons.push(button);
            return button;
        })
    );

    const panel = el('div', { className: 'onboarding__panel' }, [
        el('div', { className: 'onboarding__header' }, [
            el('img', { src: window.UNO_LOGO_SQUARE || window.UNO_LOGO, alt: '', className: 'onboarding__logo' }),
            el('div', { className: 'onboarding__header-text' }, [
                el('h2', { className: 'onboarding__title' }, ['Benvenuto in Assilevi']),
                el('p', { className: 'onboarding__subtitle' }, ['Una panoramica di come funziona, prima di iniziare.']),
            ]),
            el('button', { type: 'button', className: 'onboarding__close', title: 'Chiudi', onClick: closeOnboarding }, ['×']),
        ]),
        tabBar,
        body,
        el('div', { className: 'onboarding__footer' }, [
            el('button', { type: 'button', className: 'btn btn--primary', onClick: dismissForever }, ['Ho capito, non mostrarmelo più']),
        ]),
    ]);

    overlayEl = el(
        'div',
        {
            className: 'wizard-overlay',
            onClick: (e) => {
                if (e.target === overlayEl) closeOnboarding();
            },
        },
        [panel]
    );

    document.body.appendChild(overlayEl);
    document.addEventListener('keydown', onKeydown);
    selectTab(TABS[0].key);
}
