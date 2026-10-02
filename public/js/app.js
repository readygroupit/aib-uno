import { mountPromptShell } from './components/hero.js';
import { openGuide, loadSetupState, setupIsPending } from './onboarding.js';
import './auth.js';
import './components/data-table.js';
import './components/stat-box.js';
import './components/menu-grid.js';
import './components/form.js';
import './components/dashboard-header.js';
import './components/funnel.js';
import './components/approval-queue.js';
import './components/agent-panel.js';
import './components/profile-header.js';
import './components/permission-summary.js';
import './components/activity-feed.js';
import './components/profile-layout.js';
import './components/claim-table.js';
import './components/setup-checklist.js';
import './components/agent-feed.js';
import './components/agents-board.js';

function boot() {
    const root = document.getElementById('page-root');
    if (!root) {
        // Pagina non autenticata (login, password dimenticata...): il
        // prompt non ha senso li', #page-root non esiste nemmeno.
        return;
    }

    const dataEl = document.getElementById('page-data');
    const payload = dataEl ? JSON.parse(dataEl.textContent) : { components: [] };

    // Il prompt e' sempre presente (vedi mountPromptShell): quello che il
    // server ha renderizzato per questa pagina diventa il suo contenuto
    // iniziale, non viene montato "a parte" come prima - un solo motore
    // di rendering, mai due percorsi diversi per home e resto del sito.
    mountPromptShell(root, payload.components);
}

document.addEventListener('DOMContentLoaded', async () => {
    boot();
    if (!document.getElementById('page-root')) {
        return;
    }

    // Su ogni pagina: alimenta il riquadro rosso nella toolbar (hero.js).
    const setup = await loadSetupState();

    // La Guida si apre da sola solo sulla home. Al primo accesso (finche'
    // non e' chiusa con "Ho capito") e, dopo, ogni volta che c'e' ancora
    // qualcosa di obbligatorio da configurare - in quel caso non si puo'
    // nemmeno spegnere per sempre (vedi openGuide()).
    // window.UNO_SHOW_ONBOARDING e' gia' false da disconnessi.
    if (window.location.pathname === '/' && (window.UNO_SHOW_ONBOARDING || setupIsPending(setup))) {
        openGuide({ tab: window.UNO_SHOW_ONBOARDING ? null : 'missing' });
    }
});
