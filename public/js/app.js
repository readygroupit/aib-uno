import { mountPromptShell } from './components/hero.js';
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

document.addEventListener('DOMContentLoaded', boot);
