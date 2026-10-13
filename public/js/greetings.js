// Primo messaggio dell'assistente sulla home: una frase a caso tra queste a
// ogni apertura, con il nome di chi e' collegato e il saluto dell'ora.
// Forme neutre (niente "bentornato/a"): non sappiamo chi c'e' davanti.
const GREETINGS = [
    'Ciao {nome}! Da dove vogliamo cominciare oggi?',
    '{saluto} {nome}. Cosa ti serve per prima cosa?',
    'Eccomi {nome}! Dimmi pure, sono tutto orecchi.',
    'Pronto quando lo sei tu, {nome}. Su cosa lavoriamo?',
    '{saluto} {nome}! I colleghi digitali hanno lavorato: vuoi vedere cosa hanno preparato?',
    'Ciao {nome}! Scrivimi come parleresti a un collega, al resto penso io.',
    'Eccoci di nuovo, {nome}. Cosa mettiamo in ordine oggi?',
    '{saluto} {nome}! Un caffe\' e si parte: da cosa iniziamo?',
    'Ciao {nome}, sono qui. Chiedimi un riepilogo, una pratica o un contatto.',
    'Che piacere rivederti, {nome}! Come posso darti una mano?',
    '{saluto} {nome}. Dimmi cosa ti serve e lo troviamo insieme.',
    'Ciao {nome}! Oggi da dove partiamo: numeri, persone o pratiche?',
];

// Frase breve sotto "Ciao, sono Uno" sulla home vuota.
const SHORT_LINES = [
    'il tuo assistente. Dimmi di cosa hai bisogno.',
    'il tuo assistente. Da dove vogliamo cominciare oggi?',
    'il tuo assistente. Su cosa lavoriamo?',
    'il tuo assistente. Scrivimi come a un collega.',
    'il tuo assistente. Cosa ti serve per prima cosa?',
];

// Spunti cliccabili sotto il primo messaggio (home con cruscotto: frasi
// che il prompt sa gia' risolvere).
export const HOME_SUGGESTIONS = [
    'Fammi il riepilogo della settimana',
    'Ci sono contatti doppi?',
    'Quali documenti mancano?',
];

function salutation() {
    const hour = new Date().getHours();
    if (hour < 13) return 'Buongiorno';
    if (hour < 18) return 'Buon pomeriggio';
    return 'Buonasera';
}

function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}

export function homeGreeting() {
    const fullName = (window.UNO_USER && window.UNO_USER.name) || '';
    const firstName = fullName.split(' ')[0] || '';
    return pick(GREETINGS)
        .replace('{saluto}', salutation())
        .replace(firstName ? '{nome}' : /,? ?\{nome\}/, firstName)
        .replace(/\s+([!.?])/g, '$1')
        .trim();
}

export function homeShortLine() {
    return pick(SHORT_LINES);
}
