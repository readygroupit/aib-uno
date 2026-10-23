import { mountPromptShell } from './components/hero.js';
import { openGuide, loadSetupState, setupIsPending } from './onboarding.js';
import './auth.js';
import './components/data-table.js';
import './components/stat-box.js';
import './components/breakdown.js';
import './components/chart.js';
import './components/insight.js';
import './components/panel.js';
import './components/sheet-import.js';
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
import './components/customer-list.js';
import './components/customer-profile.js';
import './components/due-board.js';
import './components/service-summary.js';
import './components/notice.js';
import './components/work-order-board.js';
import './components/work-order-summary.js';
import './components/task-board.js';
import './components/task-summary.js';
import './components/type-steps.js';
import './components/calendar-week.js';
import './components/appointment-summary.js';
import './components/day-plan.js';
import './components/team-plan.js';
import './components/check-in.js';
import './components/worklog-week.js';
import './components/work-insights.js';
import './components/invoice-board.js';
import './components/invoice-summary.js';
import './components/editorial-board.js';
import './components/post-summary.js';
import './components/client-health.js';
import './components/media-library.js';
import './components/media-summary.js';
import './components/request-board.js';
import './components/simple-board.js';
import './components/quote-summary.js';
import { checkWorklogNudge } from './worklog-nudge.js';
import './components/setup-checklist.js';
import './components/agent-feed.js';
import './components/agent-activity.js';
import './components/agents-board.js';
import './components/projects-board.js';

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

    // Registro attivita': se e' il momento, l'avviso discreto in basso.
    checkWorklogNudge();

    // Su ogni pagina: alimenta il riquadro rosso nella toolbar (hero.js).
    const setup = await loadSetupState();

    // La Guida si apre da sola solo sulla home. Al primo accesso (finche'
    // non e' chiusa con "Ho capito") e, dopo, ogni volta che c'e' ancora
    // qualcosa di obbligatorio da configurare - in quel caso non si puo'
    // nemmeno spegnere per sempre (vedi openGuide()).
    // window.UNO_SHOW_ONBOARDING e' gia' false da disconnessi.
    // Un progetto puo' non volerla affatto all'avvio ('autoOpen' => false in
    // config/guide.php, come Uno: la home e' solo il prompt).
    const autoOpen = (window.UNO_GUIDE || {}).autoOpen !== false;
    if (window.location.pathname === '/' && ((window.UNO_SHOW_ONBOARDING && autoOpen) || setupIsPending(setup))) {
        openGuide({ tab: window.UNO_SHOW_ONBOARDING ? null : 'missing' });
    }
});
