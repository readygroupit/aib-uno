import { el } from './dom.js';
import { icon } from './icons.js';

// Parola che, detta a fine discorso, invia subito senza toccare
// tastiera/mouse. Una sola parola apposta (non una frase di due): "invia
// comando" veniva a volte spezzata dal motore vocale fra un risultato e
// il successivo e il confronto falliva - una singola parola non puo'
// tagliarsi in due pezzi.
const TRIGGER_WORD = 'esegui';

function appendChunk(base, chunk) {
    const trimmedChunk = chunk.trim();
    if (!trimmedChunk) return base;
    if (!base) return trimmedChunk;
    return base + (base.endsWith(' ') ? '' : ' ') + trimmedChunk;
}

/**
 * Aggancia la dettatura vocale a una textarea: crea il pulsante
 * microfono (il chiamante decide dove inserirlo nella propria toolbar),
 * gestisce tutto lo stato del riconoscimento, e chiama send() quando
 * viene detta la parola di invio. Estratto da hero.js in un modulo a
 * parte cosi' un fix non va rifatto due volte se in futuro serve di
 * nuovo altrove: questa logica e' gia' passata per diversi bug reali
 * (vedi i commenti nel corpo), duplicarla avrebbe rischiato di
 * reintrodurli.
 *
 * @param {HTMLTextAreaElement} input
 * @param {() => void} send
 * @returns {{ micButton: HTMLButtonElement|null, stopMic: () => void, resetSpeechState: () => void }}
 */
export function attachDictation(input, send) {
    const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognitionApi) {
        return { micButton: null, stopMic: () => {}, resetSpeechState: () => {} };
    }

    const recognition = new SpeechRecognitionApi();
    recognition.lang = 'it-IT';
    recognition.continuous = true;
    recognition.interimResults = true;

    let listening = false;
    // Testo "confermato": quello che c'era prima di iniziare ad
    // ascoltare, piu' tutti i pezzi di parlato gia' resi definitivi dal
    // motore vocale. Cresce solo per aggiunta (mai ricostruito da zero)
    // cosi' un taglio manuale del testo durante la dettatura non viene
    // "resuscitato" dal prossimo risultato vocale.
    let commitBase = '';
    // Quanti risultati di e.results sono gia' stati resi definitivi e
    // versati in commitBase - in continuous mode l'array cresce ma i
    // risultati gia' definitivi restano agli stessi indici.
    let finalizedCount = 0;
    // Vero solo mentre siamo noi a scrivere in input.value, cosi'
    // l'ascoltatore 'input' qui sotto non scambia un nostro aggiornamento
    // per una modifica manuale dell'utente (anche se impostare .value via
    // script di per se' non genera 'input', resta come rete di sicurezza).
    let applyingSpeechUpdate = false;
    // recognition.stop() e' asincrono: dopo averlo chiamato il motore
    // puo' ancora sparare uno o piu' 'result' di "coda" con lo stesso
    // transcript finale, prima che 'end' arrivi davvero - senza questo
    // guardiano la parola di invio veniva rilevata due volte e il comando
    // partiva doppio.
    let sentByWord = false;
    // Vero da quando l'utente modifica il testo a mano fino al prossimo
    // avvio esplicito dell'ascolto: il motore vocale in continuous mode
    // resta comunque attivo in background e puo' ancora consegnare un
    // risultato in ritardo (o un falso positivo su rumore/silenzio -
    // capita davvero con questa API) che si incollerebbe sopra la
    // modifica appena fatta. Con questo guardiano lo ignoriamo.
    let manualEditStop = false;

    const stopMic = () => {
        if (listening) {
            recognition.stop();
        }
    };

    // Dopo un invio (anche quello partito dalla parola vocale) il
    // riconoscimento e' fermo solo dal prossimo giro: un ultimo risultato
    // di coda poteva ancora arrivare e riscrivere nella casella appena
    // svuotata il vecchio testo (con "esegui" incluso, visto che quello
    // viene tolto solo dal valore mostrato, non da commitBase). Riusiamo
    // lo stesso guardiano delle modifiche manuali per scartare anche
    // questo caso.
    const resetSpeechState = () => {
        manualEditStop = true;
        commitBase = '';
    };

    // L'utente puo' correggere a mano il testo mentre sta ancora
    // dettando (es. cancellare una parola detta per sbaglio): quando
    // succede davvero, fermiamo subito l'ascolto - continuare a dettare
    // richiede di riattivare il microfono, ma evita che un risultato
    // vocale arrivato dopo la modifica la sovrascriva.
    input.addEventListener('input', () => {
        if (applyingSpeechUpdate) return;
        commitBase = input.value;
        if (listening) {
            manualEditStop = true;
            recognition.stop();
        }
    });

    const toggleListening = () => {
        if (listening) {
            recognition.stop();
            return;
        }
        sentByWord = false;
        manualEditStop = false;
        commitBase = input.value;
        finalizedCount = 0;
        // Il focus resta sul pulsante microfono dopo il click - senza
        // spostarlo sulla textarea il cursore non si vede in fondo al
        // testo mentre si detta (la selectionStart/End sotto non basta
        // da sola: conta solo se l'elemento e' quello a fuoco).
        input.focus();
        recognition.start();
    };

    const micButton = el(
        'button',
        { type: 'button', className: 'hero__icon-btn', title: 'Parla (F2 o Ctrl+Shift+M)', onClick: toggleListening },
        [icon('microphone')]
    );

    recognition.addEventListener('start', () => {
        listening = true;
        micButton.classList.add('is-listening');
    });

    recognition.addEventListener('end', () => {
        listening = false;
        micButton.classList.remove('is-listening');
    });

    recognition.addEventListener('error', () => {
        listening = false;
        micButton.classList.remove('is-listening');
    });

    // popola il testo man mano che si parla, non solo a fine frase
    recognition.addEventListener('result', (e) => {
        if (manualEditStop) return;

        let interim = '';
        for (let i = finalizedCount; i < e.results.length; i++) {
            const result = e.results[i];
            if (result.isFinal) {
                commitBase = appendChunk(commitBase, result[0].transcript);
                finalizedCount = i + 1;
            } else {
                interim = appendChunk(interim, result[0].transcript);
            }
        }

        let visible = appendChunk(commitBase, interim);
        // Il motore vocale aggiunge spesso un punto/virgola di fine
        // frase: va tolto prima del confronto, altrimenti "esegui." non
        // risulta mai uguale alla parola di invio.
        const normalized = visible.replace(/[.,;:!?]+$/, '').trim();
        const triggered = normalized.toLowerCase().endsWith(TRIGGER_WORD);

        if (triggered) {
            visible = normalized.slice(0, normalized.length - TRIGGER_WORD.length).trim();
        }

        applyingSpeechUpdate = true;
        input.value = visible;
        applyingSpeechUpdate = false;
        // il cursore resta alla fine, come mentre si scrive normalmente,
        // cosi' Invio funziona subito senza dover prima cliccare dentro
        input.selectionStart = input.selectionEnd = input.value.length;

        if (triggered && !sentByWord) {
            sentByWord = true;
            send();
        }
    });

    // F2 avvia/ferma il microfono con un solo tasto - scelto al posto di
    // una sequenza di lettere ripetute (es. 3 "m" veloci) perche' un
    // tasto funzione non produce mai testo: nessun rischio di scattare
    // per sbaglio mentre si scrive normalmente (una lettera comune come
    // "m" compare spessissimo in parole italiane vere, "farmacia" ne ha
    // una da sola). F2 non ha un uso riservato nei browser desktop piu'
    // comuni. Ctrl+Shift+M resta come alternativa.
    document.addEventListener('keydown', (e) => {
        if (e.key === 'F2' || (e.ctrlKey && e.shiftKey && e.key.toLowerCase() === 'm')) {
            e.preventDefault();
            toggleListening();
        }
    });

    return { micButton, stopMic, resetSpeechState };
}
