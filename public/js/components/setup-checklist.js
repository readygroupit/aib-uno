import { registerComponent } from './registry.js';
import { el } from '../dom.js';
import { mountSetup } from '../setup.js';

function renderSetupChecklist(data) {
    const body = el('div', { className: 'setup' });
    mountSetup(body, { items: data.items, progress: data.progress });

    return el('section', { className: 'card setup-card' }, [
        el('h2', { className: 'setup-card__title' }, ['Configurazione']),
        el('p', { className: 'setup-card__subtitle' }, ['Cosa serve per rendere il sistema operativo. Apri un passaggio per completarlo.']),
        body,
    ]);
}

// Spiegazione in cima alla sezione a cui porta un passaggio della
// checklist (?setup=chiave, vedi AbstractController::renderPage()).
function renderSetupHint(data) {
    return el('section', { className: 'card setup-hint' }, [
        el('span', { className: 'setup-hint__eyebrow' }, ['Configurazione']),
        el('h2', { className: 'setup-hint__title' }, [data.title]),
        el('p', { className: 'setup-hint__text' }, [data.text]),
        el('a', { className: 'btn btn--secondary btn--small', href: data.backHref }, ['Torna alla configurazione']),
    ]);
}

registerComponent('setup-checklist', renderSetupChecklist);
registerComponent('setup-hint', renderSetupHint);
