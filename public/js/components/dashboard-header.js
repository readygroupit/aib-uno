import { registerComponent } from './registry.js';
import { el, createSlidingIndicator } from '../dom.js';

// Tab di intervallo: link veri (ricarica la pagina con ?range=...), non
// stato client - stesso principio del resto del framework (GET mostra).
// L'indicatore scorrevole serve solo a posizionarsi sotto quella gia'
// attiva al caricamento, mai a spostarsi per un click (quello lo fa gia'
// la navigazione vera).
function buildRangeTabs(tabs) {
    if (!tabs || tabs.length === 0) return null;

    const container = el('div', { className: 'dashboard-header__range' });
    const buttons = tabs.map((tab) =>
        el('a', {
            className: 'dashboard-header__range-tab' + (tab.active ? ' is-active' : ''),
            href: tab.href,
        }, [tab.label])
    );
    buttons.forEach((b) => container.appendChild(b));

    const moveIndicator = createSlidingIndicator(container, 'dashboard-header__range-indicator');
    requestAnimationFrame(() => moveIndicator(container.querySelector('.is-active') || buttons[0]));

    return container;
}

function renderDashboardHeader(data) {
    const left = el('div', { className: 'dashboard-header__identity' }, [
        el('span', { className: 'dashboard-header__date' }, [data.dateLabel]),
        el('h1', { className: 'dashboard-header__greeting' }, [data.greeting]),
    ]);

    // Chi e' collegato vive nel menu profilo (vedi hero.js) - questo box
    // lo duplicava qui in cima alla home (segnalato dall'utente).
    const rangeTabs = buildRangeTabs(data.rangeTabs);

    return el('div', { className: 'dashboard-header' }, [
        left,
        el('div', { className: 'dashboard-header__right' }, rangeTabs ? [rangeTabs] : []),
    ]);
}

registerComponent('dashboard-header', renderDashboardHeader);
