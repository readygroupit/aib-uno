import { el } from '../dom.js';
import { icon } from '../icons.js';
import { mountComponents } from './registry.js';
import { attachDictation } from '../speech.js';
import { openWizard } from '../wizard.js';

// Stesso pattern di data-table.js (closeAllActionMenus): un solo listener
// a livello di documento invece che uno per menu, cosi' aprirne uno chiude
// sempre gli altri e un click ovunque fuori lo richiude.
function closeProfileMenu() {
    document.querySelectorAll('.hero__profile-menu.is-open').forEach((menu) => {
        menu.classList.remove('is-open');
    });
}

document.addEventListener('click', closeProfileMenu);

// Cronologia della CONVERSAZIONE mandata a Claude/ai tool locali - diversa
// da window.history (l'API del browser, usata sotto per il tasto indietro
// vero): nome scelto apposta per non fare ombra a quella globale.
let conversationHistory = [];

// Storico di CIO' CHE E' STATO MOSTRATO su questa pagina (diverso dalla
// conversationHistory sopra): ogni nuovo invio sostituisce il risultato in
// vista, non lo affianca al precedente. L'indice 0 e' sempre il contenuto
// REALE della pagina su cui ci si trova (quello che il server ha
// renderizzato, prompt: null - non e' stato "chiesto" con un messaggio) -
// da li' in poi ogni voce e' un risultato del prompt, con la propria
// bolla di risposta e gli eventuali suggerimenti di follow-up (vedi
// PromptConversationalInterface lato server). Se si torna indietro e poi
// si invia qualcosa di nuovo, gli eventuali risultati "futuri" oltre il
// punto in cui ci si trova vengono scartati, come la cronologia di un
// browser. Limitato per non crescere senza fine in una sessione lunga.
let resultsHistory = [];
let resultsHistoryIndex = -1;
const MAX_RESULTS_HISTORY = 20;

// Riempiti da mountPromptShell() - un solo prompt per pagina, stato
// modulo invece che richiedere di passare questi riferimenti ovunque.
let resultsPanel = null;
let messagesEl = null;

// Tipi di entita' (es. 'user') presenti nei componenti appena mostrati -
// non le righe, solo il tipo: serve a dire a Claude "l'operatore ha
// davanti questo" quando manda il prossimo messaggio (vedi 'context' in
// submitPrompt), cosi' un riferimento ambiguo come un nome proprio si puo'
// risolvere sul contesto invece di restare indovinato. La ricerca vera e
// propria (su tutta la tabella, non solo le righe visibili di una pagina
// paginata) resta comunque compito del singolo tool - qui c'e' solo il
// tipo, mai i dati.
function extractEntities(components) {
    const entities = new Set();
    (components || []).forEach((component) => {
        if (component && component.entity) {
            entities.add(component.entity);
        }
    });
    return Array.from(entities);
}

// Url della PAGINA VERA a cui corrisponde il risultato, se esiste (es.
// '/utenti') - non tutti i risultati ne hanno una (es. il catalogo
// package non ha ancora una sua route): in quel caso resta un risultato
// mostrato sul posto, senza toccare la barra degli indirizzi.
function extractUrl(components) {
    const withUrl = (components || []).find((c) => c && c.url);
    return withUrl ? withUrl.url : null;
}

// Box in vetro dei risultati, creato una volta e riusato - cercato per
// classe dentro root invece che per posizione, cosi' l'ordine con cui
// viene creato la prima volta non conta (conta solo la grid-area
// assegnata in CSS quando la sidebar e' attiva, vedi hero.css).
function getOrCreatePanel(root, className) {
    let panel = root.querySelector(':scope > .' + className);
    if (!panel) {
        panel = el('div', { className });
        root.appendChild(panel);
    }
    return panel;
}

function renderResultsEntry(panel, entry) {
    panel.textContent = '';
    if (!entry) return;

    if (resultsHistoryIndex > 0) {
        panel.appendChild(
            el(
                'button',
                {
                    type: 'button',
                    className: 'results-panel__back',
                    onClick: () => goToIndex(resultsHistoryIndex - 1),
                },
                [icon('arrow-left'), 'Risultato precedente']
            )
        );
    }

    mountComponents(panel, entry.components);
}

// Bolla di risposta lato assistente: avatar (il logo quadrato vero
// dell'app, non un personaggio inventato - "mascotte" resta un modo di
// dire, non un asset a se') + testo. Usata sia per le risposte vere sia
// per notifiche di sistema brevi (es. "non c'e' un risultato precedente"),
// stesso linguaggio visivo invece di un'area di testo separata sotto
// l'input.
function renderAssistantBubble(text) {
    return el('div', { className: 'hero__bubble-row' }, [
        el('span', { className: 'hero__mascot' }, [el('img', { src: window.UNO_LOGO_SQUARE, alt: '' })]),
        el('div', { className: 'hero__bubble hero__bubble--assistant' }, [text]),
    ]);
}

function renderUserBubble(text, active, onClick) {
    return el(
        'button',
        {
            type: 'button',
            className: 'hero__bubble hero__bubble--user' + (active ? ' hero__bubble--active' : ''),
            onClick: onClick || null,
        },
        [text]
    );
}

// Chip "prova a chiedere": domande di follow-up dichiarate dal tool
// stesso (PromptConversationalInterface::followUpSuggestions()), non
// generate da Claude a ogni risposta (costerebbe una chiamata anche per
// un match locale gratuito). Cliccarne una la esegue davvero come nuovo
// prompt (window.unoSubmitPrompt, gia' usato dal wizard).
function renderSuggestions(suggestions) {
    return el('div', { className: 'hero__suggestions' }, [
        el('span', { className: 'hero__suggestions-label' }, ['Prova a chiedere']),
        ...suggestions.map((text) =>
            el(
                'button',
                {
                    type: 'button',
                    className: 'hero__suggestion-chip',
                    onClick: () => window.unoSubmitPrompt(text),
                },
                [icon('sparkle'), text]
            )
        ),
    ]);
}

// Transcript completo: ogni voce di resultsHistory con un prompt (l'indice
// 0, il contenuto reale della pagina, non ne ha - non e' stato "chiesto")
// diventa una bolla utente cliccabile (richiama quel risultato, stessa
// funzione di goToIndex) seguita dalla bolla assistente con la sua
// risposta, se ne ha una. I suggerimenti di follow-up compaiono solo
// sotto lo scambio ATTUALMENTE in vista, non sotto ognuno - altrimenti
// una conversazione lunga si riempirebbe di chip ripetute.
function renderTranscript(container) {
    container.textContent = '';

    resultsHistory.forEach((entry, index) => {
        if (!entry.prompt) return;

        container.appendChild(renderUserBubble(entry.prompt, index === resultsHistoryIndex, () => goToIndex(index)));
        if (entry.reply) {
            container.appendChild(renderAssistantBubble(entry.reply));
        }
    });

    const current = resultsHistory[resultsHistoryIndex];
    if (current && current.suggestions && current.suggestions.length) {
        container.appendChild(renderSuggestions(current.suggestions));
    }

    container.scrollTop = container.scrollHeight;
}

// Punto unico per "vai a questo risultato dello storico" - usato dal
// pulsante "Risultato precedente", dalle bolle utente cliccabili, e da
// popstate (tasto indietro/avanti del browser). Aggiorna anche la barra
// degli indirizzi se il risultato corrisponde a una pagina vera e non ci
// siamo gia' (evita un pushState ridondante quando si arriva qui perche'
// il browser ha GIA' cambiato l'url, es. da popstate).
function goToIndex(index) {
    if (index < 0 || index >= resultsHistory.length) return;
    resultsHistoryIndex = index;
    const entry = resultsHistory[index];
    renderResultsEntry(resultsPanel, entry);
    renderTranscript(messagesEl);
    if (entry.url && window.location.pathname !== entry.url) {
        window.history.pushState(null, '', entry.url);
    }
}

/**
 * Aggiorna il risultato ATTUALMENTE in vista senza spostarsi nello
 * storico - usato da form.js dopo un submit: nel caso comune (salvataggio
 * di un contatto gia' esistente) siamo gia' sull'url giusto (es.
 * /utenti/1), e' solo il contenuto che va rinfrescato. Se pero' l'url del
 * risultato e' CAMBIATO (es. creazione: il form era su /contatti/nuovo,
 * la risposta descrive il contatto appena creato su /contatti/7), la
 * barra degli indirizzi deve seguirlo - stesso motivo di submitPrompt()
 * qui sotto, un url diverso e' una pagina diversa anche se non c'e'
 * stato un giro pagina vero.
 */
export function refreshCurrentEntry(components) {
    if (resultsHistoryIndex < 0 || !resultsHistory[resultsHistoryIndex]) return;
    const url = extractUrl(components) || resultsHistory[resultsHistoryIndex].url;
    resultsHistory[resultsHistoryIndex] = {
        ...resultsHistory[resultsHistoryIndex],
        components,
        entities: extractEntities(components),
        url,
    };
    if (url && window.location.pathname !== url) {
        window.history.pushState(null, '', url);
    }
    renderResultsEntry(resultsPanel, resultsHistory[resultsHistoryIndex]);
}

function submitPrompt(wrapEl, text) {
    if (!text || !text.trim()) return;

    wrapEl.classList.add('hero-wrap--top');
    wrapEl.parentElement.classList.add('has-history');

    // Bolla utente subito + un "..." lato assistente mentre si aspetta,
    // appesi direttamente al transcript gia' stabile (non ancora dentro
    // resultsHistory: se la richiesta fallisce non deve restare un
    // risultato fantasma navigabile, vedi il .catch() piu' sotto).
    renderTranscript(messagesEl);
    messagesEl.appendChild(renderUserBubble(text, true));
    messagesEl.appendChild(renderAssistantBubble('...'));
    messagesEl.scrollTop = messagesEl.scrollHeight;

    // Entita' del risultato ATTUALMENTE in vista (che coincide con
    // l'ultimo inviato solo se non si e' tornati indietro nello storico)
    // - e' quello che l'operatore ha davanti in questo momento, quindi e'
    // quello il contesto giusto da mandare, non necessariamente l'ultima
    // richiesta.
    const activeEntry = resultsHistoryIndex >= 0 ? resultsHistory[resultsHistoryIndex] : null;
    const context = { entities: activeEntry ? activeEntry.entities : [] };

    fetch('/prompt', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            // Corpo JSON, non un <form>: niente campo '_csrf' possibile
            // qui, il token viaggia in un header (vedi window.UNO_CSRF in
            // layout.phtml e Request::csrfHeader() lato server).
            'X-CSRF-Token': window.UNO_CSRF || '',
        },
        body: JSON.stringify({ message: text, history: conversationHistory, context }),
    })
        .then((res) => res.json())
        .then((data) => {
            conversationHistory = data.history || conversationHistory;

            if (data.components && data.components.length) {
                const url = extractUrl(data.components);

                resultsHistory = resultsHistory.slice(0, resultsHistoryIndex + 1);
                resultsHistory.push({
                    prompt: text,
                    reply: data.reply || '',
                    suggestions: data.suggestions || [],
                    components: data.components,
                    entities: extractEntities(data.components),
                    url,
                });
                if (resultsHistory.length > MAX_RESULTS_HISTORY) {
                    resultsHistory.shift();
                }
                resultsHistoryIndex = resultsHistory.length - 1;

                renderResultsEntry(resultsPanel, resultsHistory[resultsHistoryIndex]);
                renderTranscript(messagesEl);

                // Il risultato corrisponde a una pagina vera (es. la
                // richiesta era "lista permessi" mentre si era su
                // /utenti): la barra degli indirizzi deve rifletterlo,
                // altrimenti il tasto indietro del browser non avrebbe
                // nulla da "disfare" per tornare a prima di questo invio
                // - e l'url mostrato mentirebbe su cosa si sta vedendo.
                if (url && window.location.pathname !== url) {
                    window.history.pushState(null, '', url);
                }
            } else {
                // Nessun componente (domanda di chiarimento, permesso
                // negato, richiesta non capita): niente nuovo "posto" da
                // visitare, il pannello risultati resta quello di prima -
                // solo la bolla di risposta, sostituendo il "..." appeso
                // sopra.
                renderTranscript(messagesEl);
                messagesEl.appendChild(renderUserBubble(text, true));
                messagesEl.appendChild(renderAssistantBubble(data.reply || '...'));
                if (data.suggestions && data.suggestions.length) {
                    messagesEl.appendChild(renderSuggestions(data.suggestions));
                }
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
        })
        .catch(() => {
            renderTranscript(messagesEl);
            messagesEl.appendChild(renderUserBubble(text, true));
            messagesEl.appendChild(
                renderAssistantBubble("Non sono riuscito a contattare il server. Controlla la connessione e riprova.")
            );
            messagesEl.scrollTop = messagesEl.scrollHeight;
        });
}

/**
 * Costruisce e monta il prompt (una volta per pagina) dentro root
 * (#page-root). initialComponents e' cio' che il server ha gia'
 * renderizzato per QUESTA pagina (es. la lista utenti su /utenti) -
 * diventa l'ingresso 0 dello storico risultati, cosi' il prompt mostra
 * sempre "quello che c'e' davvero" prima di qualunque richiesta, invece
 * di partire vuoto ogni volta che non si e' sulla home.
 */
export function mountPromptShell(root, initialComponents) {
    const input = el('textarea', {
        className: 'hero__input',
        placeholder: 'Cosa vuoi fare?',
        rows: 2,
    });
    const attachmentsEl = el('div', { className: 'hero__attachments' });
    messagesEl = el('div', { className: 'hero__messages' });

    // Solo l'affordance: la selezione file non viene ancora inviata al
    // server (/prompt accetta solo {message, history} oggi) - andra'
    // collegata quando esistera' una capacita' reale che consuma file
    // (es. "carica questo documento sulla pratica X").
    let attachedFiles = [];

    function renderAttachments() {
        attachmentsEl.textContent = '';
        attachedFiles.forEach((file, index) => {
            attachmentsEl.appendChild(
                el('span', { className: 'hero__attachment-chip' }, [
                    file.name,
                    el(
                        'button',
                        {
                            type: 'button',
                            className: 'hero__attachment-remove',
                            onClick: () => {
                                attachedFiles.splice(index, 1);
                                renderAttachments();
                            },
                        },
                        ['×']
                    ),
                ])
            );
        });
    }

    const fileInput = el('input', {
        type: 'file',
        multiple: true,
        style: 'display:none',
        onChange: (e) => {
            attachedFiles.push(...e.target.files);
            renderAttachments();
            fileInput.value = '';
        },
    });

    // Riassegnate piu' sotto da attachDictation() - cosi' send() puo'
    // sempre spegnere il microfono e azzerare il suo stato, qualunque sia
    // stato il modo in cui e' partito l'invio (pulsante, Invio da
    // tastiera, o parola vocale).
    let stopMic = () => {};
    let resetSpeechState = () => {};

    const send = () => {
        const text = input.value;

        // Comando locale, mai mandato al server: torna al risultato
        // precedente dello storico, come cliccare "Risultato precedente"
        // - la parola d'ordine vocale "esegui" scatta comunque (es.
        // "indietro esegui" a voce), lo stesso meccanismo di qualunque
        // altro comando.
        if (text.trim().toLowerCase() === 'indietro') {
            stopMic();
            resetSpeechState();
            if (resultsHistoryIndex > 0) {
                goToIndex(resultsHistoryIndex - 1);
            } else {
                renderTranscript(messagesEl);
                messagesEl.appendChild(renderAssistantBubble("Non c'e' un risultato precedente."));
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
            input.value = '';
            input.focus();
            return;
        }

        stopMic();
        resetSpeechState();
        submitPrompt(wrap, text);
        input.value = '';
        attachedFiles = [];
        renderAttachments();
        // Il pulsante "Esegui" ruba il focus al click - lo riportiamo sulla
        // casella cosi' si puo' scrivere subito il prossimo messaggio.
        input.focus();
    };

    const attachButton = el(
        'button',
        { type: 'button', className: 'hero__icon-btn', title: 'Allega file', onClick: () => fileInput.click() },
        [icon('plus')]
    );

    // Entita' del risultato ATTUALMENTE in vista - riusata sia dalla voce
    // "Cosa posso fare?" del menu profilo sia dal contesto mandato a
    // /prompt in submitPrompt(), stesso identico ragionamento: il wizard
    // deve sapere di quale campo/entita' spiegare senza che l'operatore
    // lo debba specificare.
    const activeEntities = () => {
        const activeEntry = resultsHistoryIndex >= 0 ? resultsHistory[resultsHistoryIndex] : null;
        return activeEntry && activeEntry.entities.length ? activeEntry.entities[0] : null;
    };

    // Casa e menu: le due destinazioni "di sempre" che mancavano (segnalato
    // dall'utente - nessun modo rapido di tornare alla home o al menu).
    // Passano dal prompt (window.unoSubmitPrompt) invece che da un <a
    // href> per restare nel meccanismo SPA esistente - un link vero
    // ricaricherebbe tutto perdendo lo storico dei risultati.
    const homeButton = el(
        'button',
        { type: 'button', className: 'hero__icon-btn', title: 'Vai alla home', onClick: () => window.unoSubmitPrompt('home') },
        [icon('home')]
    );
    const menuButton = el(
        'button',
        { type: 'button', className: 'hero__icon-btn', title: 'Mostra il menu', onClick: () => window.unoSubmitPrompt('menu') },
        [icon('grid')]
    );

    // Unico punto di tutta l'app dove si arriva al proprio profilo, al
    // wizard o al logout. Il wizard era prima un'icona a se' nella
    // toolbar - spostato qui perche' non serve subito disponibile come
    // casa/menu (segnalato dall'utente): e' un aiuto da consultare
    // all'occorrenza, sta bene un click in piu' di distanza.
    const profileMenu = el('div', { className: 'hero__profile-menu' }, [
        el(
            'button',
            {
                type: 'button',
                className: 'hero__profile-menu-item',
                onClick: () => openWizard(activeEntities()),
            },
            [icon('help'), 'Cosa posso fare?']
        ),
        el('a', { className: 'hero__profile-menu-item', href: '/profilo' }, [icon('user'), 'Il mio profilo']),
        el('a', { className: 'hero__profile-menu-item', href: '/logout' }, [
            icon('arrow-right-from-bracket'),
            'Esci',
        ]),
    ]);

    const profileButton = el(
        'button',
        {
            type: 'button',
            className: 'hero__icon-btn',
            title: 'Profilo',
            onClick: (e) => {
                e.stopPropagation();
                const wasOpen = profileMenu.classList.contains('is-open');
                closeProfileMenu();
                if (!wasOpen) {
                    profileMenu.classList.add('is-open');
                }
            },
        },
        [icon('user')]
    );

    const toolbar = el('div', { className: 'hero__toolbar' }, [
        attachButton,
        homeButton,
        menuButton,
        el('div', { className: 'hero__toolbar-spacer' }),
        el('div', { className: 'hero__profile-dropdown' }, [profileButton, profileMenu]),
        // "Esegui", non "Invia": non si sta mandando un messaggio, si sta
        // chiedendo di eseguire un'operazione - stessa parola usata dal
        // comando vocale (TRIGGER_WORD in speech.js), cosi' testo e voce
        // corrispondono anche concettualmente, non solo come scorciatoia.
        // Resta anche se Invio da tastiera fa la stessa cosa (segnalato
        // dall'utente): un'azione cosi' centrale merita un'affordance
        // visibile, non solo una scorciatoia implicita.
        el('button', { type: 'button', className: 'btn btn--primary', onClick: send }, ['Esegui']),
    ]);

    const dictation = attachDictation(input, send);
    stopMic = dictation.stopMic;
    resetSpeechState = dictation.resetSpeechState;
    if (dictation.micButton) {
        toolbar.insertBefore(dictation.micButton, toolbar.children[1]);
    }

    // Icona piccola in linea con la casella - visibile solo quando il
    // prompt e' gia' in alto (schermata vuota della home esclusa: in
    // quello stato il logo grande sopra e' nascosto, e senza nessuna
    // icona non si capirebbe piu' che "e'" Uno).
    const logoInline = el('img', { src: window.UNO_LOGO, alt: 'Uno', className: 'hero__logo hero__logo--inline' });
    const inputRow = el('div', { className: 'hero__input-row' }, [logoInline, input]);

    const card = el('section', { className: 'card hero__card' }, [
        messagesEl,
        attachmentsEl,
        inputRow,
        toolbar,
        fileInput,
    ]);

    // Logo grande + saluto, visibili solo nello stato iniziale centrato
    // della home vuota (nascosti via CSS quando .hero-wrap--top).
    const logoBig = el('img', { src: window.UNO_LOGO, alt: 'Uno', className: 'hero__logo hero__logo--big' });
    const greeting = el('div', { className: 'hero__greeting' }, [
        el('p', { className: 'hero__greeting-line1' }, ['Ciao, sono Uno']),
        el('p', { className: 'hero__greeting-line2' }, ['il tuo assistente. Dimmi di cosa hai bisogno.']),
    ]);
    const intro = el('div', { className: 'hero__intro' }, [logoBig, greeting]);

    const wrap = el('div', { className: 'hero-wrap' }, [intro, card]);

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            send();
        }
    });

    window.unoSubmitPrompt = (text) => {
        input.value = text;
        send();
    };

    // Ordine in DOM importante: wrap PRIMA di resultsPanel. In modalita'
    // sidebar (CSS grid) l'ordine non conta (le grid-area riposizionano
    // visivamente), ma nel fallback compatto (schermi stretti, flusso
    // normale) si impilano nell'ordine in cui compaiono qui.
    root.appendChild(wrap);
    resultsPanel = getOrCreatePanel(root, 'results-panel');

    // L'unica situazione "vuota" e' la home senza nessun contenuto -
    // dappertutto altrove (altra pagina, o home con gia' uno storico) il
    // prompt sta sempre nella stessa posizione (sidebar sinistra, vedi
    // hero.css .has-history) invece di essere centrato in grande solo la
    // prima volta.
    const isHomePage = window.location.pathname === '/';
    const hasInitialContent = Array.isArray(initialComponents) && initialComponents.length > 0;

    if (hasInitialContent) {
        resultsHistory = [
            {
                prompt: null,
                components: initialComponents,
                entities: extractEntities(initialComponents),
                url: window.location.pathname,
            },
        ];
        resultsHistoryIndex = 0;
        renderResultsEntry(resultsPanel, resultsHistory[0]);
    }

    if (!isHomePage || hasInitialContent) {
        wrap.classList.add('hero-wrap--top');
        root.classList.add('has-history');
    }

    // Tasto indietro/avanti del browser: se punta a un url gia' presente
    // nello storico di questa sessione lo rimostriamo dalla cache locale
    // (nessuna richiesta), altrimenti lo richiediamo via AJAX (stesso
    // endpoint della navigazione normale, solo con l'header che fa
    // rispondere JSON invece di una pagina intera) e lo mostriamo senza
    // aggiungerlo allo storico (evita di confondere l'ordine di un
    // tasto indietro con una nuova voce).
    window.addEventListener('popstate', () => {
        const path = window.location.pathname;
        const cachedIndex = resultsHistory.findIndex((entry) => entry.url === path);

        if (cachedIndex !== -1) {
            resultsHistoryIndex = cachedIndex;
            renderResultsEntry(resultsPanel, resultsHistory[cachedIndex]);
            renderTranscript(messagesEl);
            return;
        }

        fetch(path, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((res) => res.json())
            .then((data) => {
                renderResultsEntry(resultsPanel, { components: data.components || [] });
            })
            .catch(() => {});
    });
}
